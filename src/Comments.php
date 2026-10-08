<?php

declare(strict_types=1);

namespace Ruvelo\Comments;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Ruvelo\Comments\Events\CommentApproved;
use Ruvelo\Comments\Events\CommentDeleted;
use Ruvelo\Comments\Events\CommentPosted;
use Ruvelo\Comments\Events\CommentUpdated;
use Ruvelo\Comments\Events\ReactionToggled;
use Ruvelo\Comments\Exceptions\CommentingClosed;
use Ruvelo\Comments\Exceptions\InvalidComment;
use Ruvelo\Comments\Exceptions\InvalidParent;
use Ruvelo\Comments\Exceptions\InvalidReaction;
use Ruvelo\Comments\Exceptions\NotAllowed;
use Ruvelo\Comments\Exceptions\NotCommentable;
use Ruvelo\Comments\Markdown\Renderer;
use Ruvelo\Comments\Models\Comment;
use Ruvelo\Comments\Models\Reaction;
use Ruvelo\Comments\Notifications\Notifier;
use Ruvelo\Comments\Support\Commentables;
use Ruvelo\Comments\Support\Thread;

/**
 * The package's public PHP API, and the one write path for comments and
 * reactions: the routes go through here too.
 *
 *     $comment = Comments::post($post, $user, 'Nice **work**');
 *     Comments::post($post, $other, 'Thanks!', parent: $comment);
 *     Comments::react($comment, $user, '🎉');
 *     Comments::for($post)->latest()->limit(5)->get();
 *
 * Nothing here checks permissions: that's the routes' job, through
 * Comments::allows(). Call it yourself when acting for a user.
 */
final class Comments
{
    /**
     * The comments readers see on a model: approved and not deleted, oldest
     * first, with their authors and reactions.
     *
     * @return Builder<Comment>
     */
    public static function for(Model $commentable): Builder
    {
        return Comment::query()
            ->on($commentable)
            ->approved()
            ->with(['author', 'reactions.reactor'])
            ->oldest('id');
    }

    /**
     * One page of top-level comments as a viewer sees them, each with its
     * replies (in `replies`), authors and reactions loaded.
     *
     * @return LengthAwarePaginator<int, Comment>
     */
    public static function thread(Model $commentable, ?Model $viewer = null, ?string $sort = null, ?int $perPage = null, ?int $page = null, string $pageName = 'page'): LengthAwarePaginator
    {
        return Thread::load(
            $commentable,
            $viewer,
            moderator: $viewer instanceof Authenticatable && self::canModerate($viewer),
            sort: self::sort($sort),
            perPage: $perPage ?? (int) config('comments.per_page', 20),
            page: $page,
            pageName: $pageName,
        );
    }

    /**
     * Post a comment, or a reply when $parent is given. Replies deeper than
     * comments.max_depth join the deepest allowed level instead.
     *
     * @throws NotCommentable
     * @throws CommentingClosed
     * @throws InvalidComment
     * @throws InvalidParent
     */
    public static function post(Model $commentable, Model $author, string $markdown, Comment|int|null $parent = null): Comment
    {
        if (! Commentables::isCommentable($commentable::class)) {
            throw new NotCommentable($commentable::class);
        }

        if (Commentables::call($commentable, 'commentsAreOpen', true) === false) {
            throw new CommentingClosed($commentable);
        }

        $body = self::body($markdown);
        $parent = self::parentFor($commentable, $parent);
        $rendered = app(Renderer::class)->render($body);

        $approved = ! config('comments.require_approval', false)
            || ($author instanceof Authenticatable && self::canModerate($author));

        $comment = new Comment([
            'parent_id' => $parent?->id,
            'root_id' => $parent === null ? null : ($parent->root_id ?? $parent->id),
            'depth' => $parent === null ? 0 : $parent->depth + 1,
            'body' => $body,
            'html' => $rendered->html,
            'approved_at' => $approved ? Carbon::now() : null,
        ]);
        $comment->commentable()->associate($commentable);
        $comment->author()->associate($author);
        $comment->save();

        event(new CommentPosted($comment));

        if ($approved) {
            Notifier::published($comment, $rendered->mentions);
        }

        return $comment;
    }

    /**
     * Replace a comment's text. Marks it edited; people newly mentioned are
     * notified.
     *
     * @throws InvalidComment
     */
    public static function update(Comment $comment, string $markdown): Comment
    {
        $body = self::body($markdown);

        if ($body === $comment->body) {
            return $comment;
        }

        $renderer = app(Renderer::class);
        $before = $renderer->render($comment->body)->mentions;
        $rendered = $renderer->render($body);

        $comment->forceFill([
            'body' => $body,
            'html' => $rendered->html,
            'edited_at' => Carbon::now(),
        ])->save();

        event(new CommentUpdated($comment));

        if ($comment->isApproved()) {
            $known = array_map(fn (Model $person): string => $person->getMorphClass().':'.$person->getKey(), $before);
            Notifier::mentioned($comment, array_values(array_filter(
                $rendered->mentions,
                fn (Model $person): bool => ! in_array($person->getMorphClass().':'.$person->getKey(), $known, true),
            )));
        }

        return $comment;
    }

    /**
     * Delete a comment. Its replies stay; it shows as "This comment was
     * deleted" while it has any.
     */
    public static function delete(Comment $comment, ?Model $by = null): void
    {
        if ($comment->trashed()) {
            return;
        }

        $comment->delete();

        event(new CommentDeleted($comment, $by));
    }

    /**
     * Add the reaction, or take it back if the person already gave it.
     * Returns true when it was added.
     *
     * @throws InvalidReaction
     */
    public static function react(Comment $comment, Model $reactor, string $emoji): bool
    {
        if (! in_array($emoji, self::reactions(), true)) {
            throw new InvalidReaction($emoji);
        }

        $existing = $comment->reactions()
            ->where('reactor_type', $reactor->getMorphClass())
            ->where('reactor_id', $reactor->getKey())
            ->where('emoji', $emoji)
            ->first();

        if ($existing !== null) {
            $existing->delete();
            $added = false;
        } else {
            try {
                $reaction = new Reaction(['emoji' => $emoji]);
                $reaction->comment()->associate($comment);
                $reaction->reactor()->associate($reactor);
                $reaction->save();
            } catch (UniqueConstraintViolationException) {
                // A double click raced us; the reaction is there either way.
            }
            $added = true;
        }

        $comment->unsetRelation('reactions');

        event(new ReactionToggled($comment, $reactor, $emoji, $added));

        return $added;
    }

    /**
     * Publish a comment waiting in the moderation queue.
     */
    public static function approve(Comment $comment): Comment
    {
        if ($comment->isApproved()) {
            return $comment;
        }

        $comment->forceFill(['approved_at' => Carbon::now()])->save();

        event(new CommentApproved($comment));

        Notifier::published($comment, app(Renderer::class)->render($comment->body)->mentions);

        return $comment;
    }

    /**
     * Markdown to the same safe HTML comments get.
     */
    public static function render(string $markdown): string
    {
        return app(Renderer::class)->render($markdown)->html;
    }

    /**
     * Approved comments on the model that aren't deleted. For lists of many
     * models, use the withCommentsCount() scope instead.
     */
    public static function countFor(Model $commentable): int
    {
        return Comment::query()->on($commentable)->approved()->count();
    }

    /**
     * Where a comment can be seen: its permalink, which redirects to the
     * commentable's commentUrl() with the comment's anchor.
     */
    public static function url(Comment $comment): ?string
    {
        if (Route::has('comments.show')) {
            return route('comments.show', $comment);
        }

        $url = Commentables::call($comment->commentable, 'commentUrl');

        return is_string($url) ? $url.'#'.$comment->anchor() : null;
    }

    /**
     * Whether the user may do something. Abilities: comments-create
     * (commentable), comments-update, comments-delete and comments-react
     * (comment), comments-moderate. Define a gate with the same name to
     * decide yourself; otherwise the defaults apply:
     *
     * - create, react: any signed-in user
     * - update: the author, within comments.edit_window
     * - delete: the author, or a moderator
     * - moderate: nobody
     */
    public static function allows(string $ability, ?Authenticatable $user, mixed ...$arguments): bool
    {
        if (Gate::has($ability)) {
            return Gate::forUser($user)->allows($ability, array_values($arguments));
        }

        $subject = $arguments[0] ?? null;
        $comment = $subject instanceof Comment ? $subject : null;
        $own = $comment !== null && $user instanceof Model && $comment->isAuthoredBy($user);

        return match ($ability) {
            'comments-create' => $user !== null,
            'comments-react' => $user !== null && $comment !== null && ! $comment->trashed() && $comment->isApproved(),
            'comments-update' => $own && ! $comment->trashed() && self::withinEditWindow($comment),
            'comments-delete' => $comment !== null && ! $comment->trashed() && ($own || self::canModerate($user)),
            'comments-moderate' => false,
            default => false,
        };
    }

    /**
     * @throws NotAllowed
     */
    public static function authorize(string $ability, ?Authenticatable $user, mixed ...$arguments): void
    {
        if (! self::allows($ability, $user, ...$arguments)) {
            throw new NotAllowed($ability);
        }
    }

    public static function canModerate(?Authenticatable $user): bool
    {
        return self::allows('comments-moderate', $user);
    }

    /**
     * The emoji people can react with.
     *
     * @return list<string>
     */
    public static function reactions(): array
    {
        return array_values(array_filter(config('comments.reactions', []), is_string(...)));
    }

    public static function maxDepth(): int
    {
        return max(1, (int) config('comments.max_depth', 2));
    }

    public static function sort(?string $sort): string
    {
        $sort ??= config('comments.sort', 'oldest');

        return $sort === 'newest' ? 'newest' : 'oldest';
    }

    /**
     * @return class-string<Model>
     */
    public static function userModel(): string
    {
        return config('auth.providers.users.model') ?? 'App\\Models\\User';
    }

    private static function withinEditWindow(Comment $comment): bool
    {
        $minutes = config('comments.edit_window');

        return $minutes === null || $comment->created_at->greaterThan(Carbon::now()->subMinutes((int) $minutes));
    }

    /**
     * @throws InvalidComment
     */
    private static function body(string $markdown): string
    {
        $body = trim(str_replace(["\r\n", "\r"], "\n", $markdown));
        $max = (int) config('comments.max_length', 5000);

        if ($body === '') {
            throw InvalidComment::empty();
        }

        if (mb_strlen($body) > $max) {
            throw InvalidComment::tooLong($max);
        }

        return $body;
    }

    /**
     * The comment a reply hangs from, after capping the depth.
     *
     * @throws InvalidParent
     */
    private static function parentFor(Model $commentable, Comment|int|null $parent): ?Comment
    {
        if ($parent === null) {
            return null;
        }

        $parent = $parent instanceof Comment ? $parent : Comment::withTrashed()->find($parent);

        if ($parent === null
            || $parent->commentable_type !== $commentable->getMorphClass()
            || (string) $parent->commentable_id !== (string) $commentable->getKey()) {
            throw new InvalidParent;
        }

        if ($parent->trashed() || $parent->isPending()) {
            throw new InvalidParent('That comment was deleted or isn’t published yet, so it can’t be replied to.');
        }

        while ($parent !== null && $parent->depth >= self::maxDepth() - 1) {
            $parent = $parent->parent_id === null ? null : $parent->parent;
        }

        return $parent;
    }
}
