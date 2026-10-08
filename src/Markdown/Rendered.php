<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Markdown;

use Illuminate\Database\Eloquent\Model;

/**
 * A rendered comment body: safe HTML and the people it mentions.
 */
final class Rendered
{
    /**
     * @param  list<Model>  $mentions
     */
    public function __construct(
        public readonly string $html,
        public readonly array $mentions = [],
    ) {}
}
