<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Markdown;

use League\CommonMark\Node\Inline\AbstractInline;

/**
 * A mention of someone with no page to link to.
 *
 * @internal
 */
final class MentionText extends AbstractInline
{
    public function __construct(public readonly string $handle)
    {
        parent::__construct();
    }
}
