<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Markdown;

use Illuminate\Database\Eloquent\Model;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Mention\Mention;
use League\CommonMark\Extension\Mention\MentionParser;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Inline\AbstractInline;
use League\CommonMark\Util\RegexHelper;
use Ruvelo\Comments\Contracts\MentionResolver;

/**
 * Markdown to safe HTML. Raw HTML is escaped, javascript:-style links are
 * dropped, links get rel="nofollow ugc", images become links, and
 * `@handles` become mentions when a resolver is configured.
 *
 * The CommonMark pipeline is built once and reused; each render runs at
 * most one query, to resolve the handles it contains.
 */
class Renderer
{
    /** Handles: a letter, digit or underscore, then dots and dashes allowed inside. */
    public const HANDLE = '[A-Za-z0-9_](?:[A-Za-z0-9_.\-]*[A-Za-z0-9_])?';

    private ?MarkdownConverter $converter = null;

    /** @var array<string, Model> */
    private array $people = [];

    /** @var array<string, Model> */
    private array $mentioned = [];

    public function render(string $markdown): Rendered
    {
        $resolver = $this->resolver();
        $this->mentioned = [];
        $this->people = [];

        if ($resolver !== null && preg_match_all('/(?<![\w@])@('.self::HANDLE.')/u', $markdown, $matches)) {
            $handles = array_values(array_unique(array_map(strtolower(...), $matches[1])));
            $this->people = array_change_key_case($resolver->resolve($handles), CASE_LOWER);
        }

        $html = (string) $this->converter()->convert($markdown);

        return new Rendered($html, $this->mentioned());
    }

    /**
     * Rebuild the pipeline on next use, e.g. after changing the config.
     */
    public function reset(): void
    {
        $this->converter = null;
    }

    public function resolver(): ?MentionResolver
    {
        $resolver = config('comments.mentions.resolver');

        if ($resolver === null || $resolver === '') {
            return null;
        }

        $resolver = is_string($resolver) ? app($resolver) : $resolver;

        return $resolver instanceof MentionResolver ? $resolver : null;
    }

    /**
     * The people the last render mentioned, collected by mention().
     *
     * @return list<Model>
     */
    private function mentioned(): array
    {
        return array_values($this->mentioned);
    }

    private function converter(): MarkdownConverter
    {
        return $this->converter ??= $this->build();
    }

    private function build(): MarkdownConverter
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        $environment = new Environment(array_replace_recursive([
            'external_link' => [
                'internal_hosts' => is_string($host) ? [$host] : [],
                'nofollow' => 'external',
                'noopener' => 'external',
                'noreferrer' => '',
            ],
            'max_nesting_level' => 20,
        ], config('comments.markdown.options', []), [
            // Not configurable: anyone can write a comment.
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]));

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new ExternalLinkExtension);

        foreach (config('comments.markdown.extensions', []) as $extension) {
            /** @var ExtensionInterface $extension */
            $extension = is_string($extension) ? app($extension) : $extension;
            $environment->addExtension($extension);
        }

        $environment->addRenderer(Image::class, new ImageAsLinkRenderer, 10);
        $environment->addRenderer(MentionText::class, new MentionTextRenderer);
        $environment->addInlineParser(MentionParser::createWithCallback('comments', '@', self::HANDLE, $this->mention(...)), 100);

        return new MarkdownConverter($environment);
    }

    private function mention(Mention $mention): ?AbstractInline
    {
        $key = strtolower($mention->getIdentifier());
        $person = $this->people[$key] ?? null;

        if ($person === null) {
            return null;
        }

        $this->mentioned[$person->getMorphClass().':'.$person->getKey()] = $person;

        $url = $this->resolver()?->url($person);

        if ($url === null || RegexHelper::isLinkPotentiallyUnsafe($url)) {
            return new MentionText($mention->getIdentifier());
        }

        $mention->setUrl($url);
        $mention->data->set('attributes/class', 'comments-mention');

        return $mention;
    }
}
