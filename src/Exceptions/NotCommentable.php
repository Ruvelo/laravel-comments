<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Exceptions;

/**
 * The type isn't in comments.commentables or the morph map, or its model
 * doesn't use HasComments. Class names from requests are never trusted.
 */
final class NotCommentable extends CommentsException
{
    public function __construct(public readonly string $type)
    {
        parent::__construct("“{$type}” can’t be commented on. Add it to comments.commentables and use the HasComments trait.");
    }

    public function status(): int
    {
        return 404;
    }
}
