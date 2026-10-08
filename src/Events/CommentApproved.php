<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Comments\Models\Comment;

/**
 * Fired when a moderator approves a pending comment.
 */
final class CommentApproved
{
    use Dispatchable;

    public function __construct(
        public readonly Comment $comment,
    ) {}
}
