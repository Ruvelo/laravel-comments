<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Comments\Models\Comment;

/**
 * Fired after an author edits their comment.
 */
final class CommentUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly Comment $comment,
    ) {}
}
