<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Exceptions;

/**
 * The author hit comments.rate_limit.
 */
final class TooManyComments extends CommentsException
{
    public function __construct(public readonly int $retryAfter)
    {
        $wait = $retryAfter < 60 ? $retryAfter.' seconds' : (int) ceil($retryAfter / 60).' minutes';

        parent::__construct("You’re posting quickly. Try again in {$wait}.");
    }

    public function status(): int
    {
        return 429;
    }
}
