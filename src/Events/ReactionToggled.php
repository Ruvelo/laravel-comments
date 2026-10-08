<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Comments\Models\Comment;

/**
 * Fired when someone adds ($added) or removes a reaction.
 */
final class ReactionToggled
{
    use Dispatchable;

    public function __construct(
        public readonly Comment $comment,
        public readonly Model $reactor,
        public readonly string $emoji,
        public readonly bool $added,
    ) {}
}
