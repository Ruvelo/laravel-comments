<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Exceptions;

/**
 * The comment being replied to is on another model, deleted, or not
 * approved yet.
 */
final class InvalidParent extends CommentsException
{
    public function __construct(string $message = 'That comment can’t be replied to.')
    {
        parent::__construct($message);
    }
}
