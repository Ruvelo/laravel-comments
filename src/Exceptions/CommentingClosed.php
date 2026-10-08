<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Exceptions;

use Illuminate\Database\Eloquent\Model;

/**
 * The model's commentsAreOpen() said no.
 */
final class CommentingClosed extends CommentsException
{
    public function __construct(public readonly Model $commentable)
    {
        parent::__construct('Comments are closed here.');
    }

    public function status(): int
    {
        return 403;
    }
}
