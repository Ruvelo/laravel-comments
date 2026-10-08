<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Exceptions;

/**
 * A comments-* gate refused the action.
 */
final class NotAllowed extends CommentsException
{
    public function __construct(public readonly string $ability, string $message = 'You can’t do that.')
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return 403;
    }
}
