<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Comments\Models\Comment;

/**
 * Fired after a comment is deleted (soft-deleted: replies keep their place).
 * $by is who deleted it, when known: the author or a moderator.
 */
final class CommentDeleted
{
    use Dispatchable;

    public function __construct(
        public readonly Comment $comment,
        public readonly ?Model $by = null,
    ) {}
}
