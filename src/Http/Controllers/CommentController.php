<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Ruvelo\Comments\Comments;
use Ruvelo\Comments\Exceptions\NotCommentable;
use Ruvelo\Comments\Exceptions\TooManyComments;
use Ruvelo\Comments\Http\Resources\CommentResource;
use Ruvelo\Comments\Models\Comment;
use Ruvelo\Comments\Support\Commentables;
use Ruvelo\Comments\View\ThreadContext;

/**
 * Every comment route answers both ways: a redirect back to the page for
 * plain HTML forms, JSON (with a ready-rendered HTML fragment) for the
 * thread's script and for SPAs.
 */
class CommentController extends Controller
{
    /**
     * GET threads/{type}/{id}?sort=newest&page=2
     */
    public function index(Request $request, string $type, string $id): AnonymousResourceCollection
    {
        $commentable = $this->commentable($type, $id);
        $viewer = $request->user();

        $comments = Comments::thread(
            $commentable,
            $viewer instanceof Model ? $viewer : null,
            $request->string('sort')->toString() ?: null,
            min(max($request->integer('per_page', (int) config('comments.per_page', 20)), 1), 100),
        )->withQueryString();

        return CommentResource::collection($comments)->additional(['meta' => [
            'comments_count' => Comments::countFor($commentable),
            'open' => Commentables::call($commentable, 'commentsAreOpen', true) !== false,
            'can_comment' => (new ThreadContext($commentable, $viewer))->canCreate,
            'reactions' => Comments::reactions(),
            'max_depth' => Comments::maxDepth(),
        ]]);
    }

    /**
     * POST threads/{type}/{id} {body, parent_id?}
     */
    public function store(Request $request, string $type, string $id): JsonResponse|RedirectResponse
    {
        $commentable = $this->commentable($type, $id);
        $user = $request->user();

        Comments::authorize('comments-create', $user, $commentable);

        if ($this->isSpam($request)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Thanks.'], 202)
                : back();
        }

        $data = $this->validated($request, ['parent_id' => ['nullable', 'integer']]);

        if ($preview = $this->noScriptPreview($request, $data['body'])) {
            return $preview;
        }

        $this->throttle($request);

        $comment = Comments::post($commentable, $this->author($request), $data['body'], isset($data['parent_id']) ? (int) $data['parent_id'] : null);

        $message = $comment->isApproved() ? 'Comment posted.' : 'Thanks! Your comment will appear once a moderator approves it.';

        if (! $request->expectsJson()) {
            return $this->back($comment, $message);
        }

        $comment->setRelation('commentable', $commentable)->setRelation('replies', new Collection);
        $comment->load(['author', 'reactions']);

        return (new CommentResource($comment))->additional([
            'message' => $message,
            'comments_count' => Comments::countFor($commentable),
            'html' => view('comments::partials.comment', [
                'comment' => $comment,
                'ctx' => new ThreadContext($commentable, $user),
            ])->render(),
        ])->response()->setStatusCode(201);
    }

    /**
     * GET {id}: the comment's permalink, which redirects to its page.
     */
    public function show(string $commentId): RedirectResponse
    {
        $comment = Comment::withTrashed()->with('commentable')->findOrFail($commentId);
        $url = Commentables::call($comment->commentable, 'commentUrl');

        abort_unless(is_string($url) && $url !== '', 404);

        return redirect()->to(strtok($url, '#').'#'.$comment->anchor());
    }

    /**
     * PATCH {id} {body}
     */
    public function update(Request $request, string $commentId): JsonResponse|RedirectResponse
    {
        $comment = $this->find($commentId);

        Comments::authorize('comments-update', $request->user(), $comment);

        $data = $this->validated($request);

        if ($preview = $this->noScriptPreview($request, $data['body'])) {
            return $preview;
        }

        Comments::update($comment, $data['body']);

        return $this->respond($request, $comment, 'Comment updated.');
    }

    /**
     * DELETE {id}
     */
    public function destroy(Request $request, string $commentId): JsonResponse|RedirectResponse
    {
        $comment = $this->find($commentId);
        $user = $request->user();

        Comments::authorize('comments-delete', $user, $comment);
        Comments::delete($comment, $user instanceof Model ? $user : null);

        return $this->respond($request, $comment, 'Comment deleted.');
    }

    /**
     * POST {id}/reactions {emoji}
     */
    public function react(Request $request, string $commentId): JsonResponse|RedirectResponse
    {
        $comment = $this->find($commentId);
        $user = $request->user();

        Comments::authorize('comments-react', $user, $comment);

        $data = $request->validate(['emoji' => ['required', 'string', 'max:32']]);

        $added = Comments::react($comment, $this->author($request), $data['emoji']);

        if (! $request->expectsJson()) {
            return redirect()->to($this->previous().'#'.$comment->anchor());
        }

        $comment->load('reactions.reactor');

        return response()->json([
            'added' => $added,
            'reactions' => $comment->reactionSummary($this->author($request)),
            'html' => view('comments::partials.reactions', [
                'comment' => $comment,
                'ctx' => new ThreadContext($comment->commentable ?? abort(404), $user),
            ])->render(),
        ]);
    }

    /**
     * POST preview {body}: Markdown in, the HTML a comment would get out.
     */
    public function preview(Request $request): JsonResponse|Response
    {
        $data = $request->validate(['body' => ['nullable', 'string', 'max:'.((int) config('comments.max_length', 5000) * 2)]]);
        $html = Comments::render((string) ($data['body'] ?? ''));

        return $request->expectsJson()
            ? response()->json(['html' => $html])
            : response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function commentable(string $type, string $id): Model
    {
        try {
            return Commentables::find($type, $id);
        } catch (NotCommentable) {
            abort(404);
        }
    }

    /**
     * The signed-in user, who comments and reacts in their own name.
     */
    private function author(Request $request): Model
    {
        $user = $request->user();

        abort_unless($user instanceof Model, 401);

        return $user;
    }

    private function find(string $id): Comment
    {
        return Comment::query()->with('commentable')->findOrFail($id);
    }

    /**
     * @param  array<string, list<string>>  $rules
     * @return array{body: string, parent_id?: int|string|null}
     */
    private function validated(Request $request, array $rules = []): array
    {
        $max = (int) config('comments.max_length', 5000);

        /** @var array{body: string, parent_id?: int|string|null} */
        return $request->validateWithBag('comments', [
            'body' => ['required', 'string', 'max:'.$max],
            ...$rules,
        ], [
            'body.required' => 'Write something before posting.',
            'body.max' => 'Keep comments under '.number_format($max).' characters.',
        ]);
    }

    private function isSpam(Request $request): bool
    {
        $field = config('comments.honeypot');

        return is_string($field) && $field !== '' && filled($request->input($field));
    }

    /**
     * Without JavaScript, the form's Preview button posts here; send the
     * text back with its preview.
     */
    private function noScriptPreview(Request $request, string $body): ?RedirectResponse
    {
        if ($request->input('action') !== 'preview' || $request->expectsJson()) {
            return null;
        }

        return redirect()->to($this->previous().'#'.$request->input('_comments_form', 'comments'))
            ->withInput($request->except(['_token', '_method', 'action']))
            ->with('comments.preview', Comments::render($body));
    }

    /**
     * @throws TooManyComments
     */
    private function throttle(Request $request): void
    {
        $max = config('comments.rate_limit.max');

        if ($max === null) {
            return;
        }

        $user = $this->author($request);
        $key = 'comments:'.$user->getMorphClass().':'.$user->getKey();

        if (RateLimiter::tooManyAttempts($key, (int) $max)) {
            throw new TooManyComments(RateLimiter::availableIn($key));
        }

        RateLimiter::hit($key, (int) config('comments.rate_limit.minutes', 1) * 60);
    }

    private function respond(Request $request, Comment $comment, string $message): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return $this->back($comment, $message);
        }

        $comment->load(['author', 'reactions.reactor']);
        $user = $request->user();

        return (new CommentResource($comment))->additional([
            'message' => $message,
            'comments_count' => Comments::countFor($comment->commentable ?? abort(404)),
            'html' => view('comments::partials.card', [
                'comment' => $comment,
                'ctx' => new ThreadContext($comment->commentable, $user),
            ])->render(),
        ])->response();
    }

    private function back(Comment $comment, string $message): RedirectResponse
    {
        return redirect()->to($this->previous().'#'.$comment->anchor())->with('comments.status', $message);
    }

    private function previous(): string
    {
        return (string) strtok(url()->previous(), '#');
    }
}
