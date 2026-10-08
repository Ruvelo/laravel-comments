<?php

declare(strict_types=1);

namespace Ruvelo\Comments\View;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Ruvelo\Comments\Comments;
use Ruvelo\Comments\Models\Comment;
use Ruvelo\Comments\Support\Commentables;

/**
 * What the thread views need to know about who's looking: shared by the
 * Blade component and the HTML fragments the routes return to the script.
 *
 * @internal
 */
final class ThreadContext
{
    public readonly string $type;

    public readonly bool $open;

    public readonly bool $canCreate;

    public readonly bool $canModerate;

    /** @var list<string> */
    public readonly array $reactions;

    public readonly int $maxDepth;

    public function __construct(
        public readonly Model $commentable,
        public readonly ?Authenticatable $viewer,
        public readonly string $sort = 'oldest',
    ) {
        $this->type = Commentables::alias($commentable);
        $this->open = Commentables::call($commentable, 'commentsAreOpen', true) !== false;
        $this->canCreate = $this->open && Comments::allows('comments-create', $viewer, $commentable);
        $this->canModerate = Comments::canModerate($viewer);
        $this->reactions = Comments::reactions();
        $this->maxDepth = Comments::maxDepth();
    }

    public static function for(Model $commentable, ?Authenticatable $viewer, ?string $sort = null): self
    {
        return new self($commentable, $viewer, Comments::sort($sort));
    }

    public function viewerModel(): ?Model
    {
        return $this->viewer instanceof Model ? $this->viewer : null;
    }

    public function canReply(Comment $comment): bool
    {
        return $this->canCreate && ! $comment->trashed() && $comment->isApproved();
    }

    public function canUpdate(Comment $comment): bool
    {
        return Comments::allows('comments-update', $this->viewer, $comment);
    }

    public function canDelete(Comment $comment): bool
    {
        return Comments::allows('comments-delete', $this->viewer, $comment);
    }

    public function canReact(Comment $comment): bool
    {
        return $this->reactions !== [] && Comments::allows('comments-react', $this->viewer, $comment);
    }

    public function storeUrl(): string
    {
        return route('comments.store', ['type' => $this->type, 'id' => $this->commentable->getKey()]);
    }
}
