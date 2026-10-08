<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Markdown;

use InvalidArgumentException;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

/**
 * @internal
 */
final class MentionTextRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
    {
        if (! $node instanceof MentionText) {
            throw new InvalidArgumentException('Expected a mention, got '.$node::class);
        }

        return new HtmlElement('span', ['class' => 'comments-mention'], Xml::escape('@'.$node->handle));
    }
}
