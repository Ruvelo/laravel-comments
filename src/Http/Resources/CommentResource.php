<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ruvelo\Comments\Comments;
use Ruvelo\Comments\Models\Comment;
use Ruvelo\Comments\Support\Commentables;

/**
 * A comment as the JSON API returns it. A deleted comment keeps its place
 * (and its replies) but loses its author, text and reactions.
 *
 * @mixin Comment
 */
final class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Comment $comment */
        $comment = $this->resource;
        $user = $request->user();
        $deleted = $comment->trashed();
        $open = Commentables::call($comment->commentable, 'commentsAreOpen', true) !== false;

        return [
            'id' => $comment->id,
            'parent_id' => $comment->parent_id,
            'depth' => $comment->depth,
            'author' => $deleted ? null : [
                'name' => $comment->authorName(),
                'initials' => $comment->authorInitials(),
                'avatar_url' => $comment->authorAvatarUrl(),
            ],
            'body' => $deleted ? null : $comment->body,
            'html' => $deleted ? null : $comment->html,
            'created_at' => $comment->created_at->toIso8601String(),
            'edited_at' => $deleted ? null : $comment->edited_at?->toIso8601String(),
            'approved' => $comment->isApproved(),
            'deleted' => $deleted,
            'url' => Comments::url($comment),
            'reactions' => $deleted ? [] : $comment->reactionSummary($user instanceof Model ? $user : null),
            'can' => [
                'reply' => $open && ! $deleted && $comment->isApproved() && Comments::allows('comments-create', $user, $comment->commentable),
                'update' => Comments::allows('comments-update', $user, $comment),
                'delete' => Comments::allows('comments-delete', $user, $comment),
                'react' => Comments::reactions() !== [] && Comments::allows('comments-react', $user, $comment),
            ],
            'replies' => $comment->relationLoaded('replies') ? self::collection($comment->replies) : [],
        ];
    }
}
