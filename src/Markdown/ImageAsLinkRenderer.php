<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Markdown;

use InvalidArgumentException;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\RegexHelper;
use League\CommonMark\Util\Xml;

/**
 * Images in comments become links: anyone can comment, and an embedded
 * image would make every reader's browser fetch a stranger's URL.
 *
 * @internal
 */
final class ImageAsLinkRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement|string
    {
        if (! $node instanceof Image) {
            throw new InvalidArgumentException('Expected an image, got '.$node::class);
        }

        $label = $childRenderer->renderNodes($node->children());

        if (RegexHelper::isLinkPotentiallyUnsafe($node->getUrl())) {
            return $label;
        }

        if ($label === '') {
            $label = Xml::escape($node->getUrl());
        }

        return new HtmlElement('a', ['href' => $node->getUrl(), 'rel' => 'nofollow ugc noopener'], $label);
    }
}
