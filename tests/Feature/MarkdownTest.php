<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Ruvelo\Comments\Comments;
use Ruvelo\Comments\Markdown\Renderer;
use Ruvelo\Comments\Mentions\ColumnResolver;
use Ruvelo\Comments\Tests\TestCase;

class MarkdownTest extends TestCase
{
    public function test_raw_html_is_escaped(): void
    {
        $html = Comments::render("<script>alert(1)</script>\n\nHi <img src=x onerror=alert(1)> <b>bold</b>");

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_unsafe_links_are_dropped(): void
    {
        foreach (['javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'vbscript:msgbox(1)', 'data:text/html;base64,PHNjcmlwdD4='] as $url) {
            $html = Comments::render("[click]({$url})");

            $this->assertStringNotContainsString(strtolower(explode(':', $url)[0]).':', strtolower($html), $url);
        }
    }

    public function test_attribute_injection_through_link_titles_and_urls_is_escaped(): void
    {
        $html = Comments::render('[x](https://example.com/" onmouseover="alert(1) "t\"itle")');

        $this->assertStringNotContainsString('onmouseover="alert', $html);
    }

    public function test_external_links_are_marked_nofollow(): void
    {
        $html = Comments::render('[spam](https://spam.example) and [ours](https://app.test/docs)');

        $this->assertMatchesRegularExpression('#<a rel="[^"]*nofollow[^"]*" href="https://spam.example">#', $html);
        $this->assertStringContainsString('<a href="https://app.test/docs">ours</a>', $html);
    }

    public function test_images_become_links_so_readers_never_load_strangers_urls(): void
    {
        $html = Comments::render('![a chart](https://evil.example/pixel.png) ![](javascript:alert(1))');

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('<a href="https://evil.example/pixel.png" rel="nofollow ugc noopener">a chart</a>', $html);
        $this->assertStringNotContainsString('href="javascript', $html);
    }

    public function test_code_blocks_and_gfm_work(): void
    {
        $html = Comments::render("```php\necho '<b>';\n```\n\n~~old~~ | a |\n|---|---|");

        $this->assertStringContainsString('<pre><code class="language-php">echo \'&lt;b&gt;\';', $html);
        $this->assertStringContainsString('<del>old</del>', $html);
    }

    public function test_html_input_cannot_be_unlocked_from_config(): void
    {
        config(['comments.markdown.options' => ['html_input' => 'allow', 'allow_unsafe_links' => true]]);
        app(Renderer::class)->reset();

        $html = Comments::render('<script>x</script> [a](javascript:x)');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('href="javascript', $html);
    }

    public function test_mentions_are_off_without_a_resolver(): void
    {
        $this->user('Maya Okafor');

        $this->assertSame("<p>Thanks @maya</p>\n", Comments::render('Thanks @maya'));
    }

    public function test_mentions_resolve_through_the_column_resolver(): void
    {
        config(['comments.mentions.resolver' => ColumnResolver::class]);
        $this->user('Maya Okafor');
        $this->user('Tom Reyes');

        $html = Comments::render('Thanks @Maya and @tom, not @nobody or maya@example.com or `@maya`');

        $this->assertStringContainsString('Thanks <span class="comments-mention">@Maya</span> and <span class="comments-mention">@tom</span>', $html);
        $this->assertStringContainsString('not @nobody', $html);
        $this->assertStringContainsString('<code>@maya</code>', $html);
        $this->assertStringNotContainsString('<span class="comments-mention">@example', $html);
    }

    public function test_mentions_link_to_the_configured_route(): void
    {
        Route::get('/people/{user}', fn () => 'profile')->name('people.show');
        app('router')->getRoutes()->refreshNameLookups();
        config(['comments.mentions.resolver' => ColumnResolver::class, 'comments.mentions.route' => 'people.show']);
        $maya = $this->user('Maya Okafor');

        $html = Comments::render('Hey @maya');

        $this->assertMatchesRegularExpression('#<a class="comments-mention"[^>]* href="[^"]+/people/'.$maya->id.'">@maya</a>#', $html);
    }

    public function test_underscores_in_handles_are_not_wildcards(): void
    {
        config(['comments.mentions.resolver' => ColumnResolver::class]);
        $this->user('Maya Okafor', ['username' => 'maxa']);

        $this->assertStringNotContainsString('comments-mention', Comments::render('@ma_a'));
    }
}
