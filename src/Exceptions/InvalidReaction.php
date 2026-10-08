<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Exceptions;

/**
 * The emoji isn't in comments.reactions.
 */
final class InvalidReaction extends CommentsException
{
    public function __construct(public readonly string $emoji)
    {
        parent::__construct('Pick one of the offered reactions.');
    }
}
