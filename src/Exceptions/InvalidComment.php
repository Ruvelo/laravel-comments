<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Exceptions;

/**
 * The body is empty or longer than comments.max_length.
 */
final class InvalidComment extends CommentsException
{
    public static function empty(): self
    {
        return new self('Write something before posting.');
    }

    public static function tooLong(int $max): self
    {
        return new self('Keep comments under '.number_format($max).' characters.');
    }
}
