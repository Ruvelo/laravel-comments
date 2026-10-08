<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Comments\Models\Comment;

/**
 * Fired after a comment or reply is posted, whether or not it needs approval (check $comment->isApproved()).
 */
final class CommentPosted
{
    use Dispatchable;

    public function __construct(
        public readonly Comment $comment,
    ) {}
}
