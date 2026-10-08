<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Ruvelo\Comments\Tests\TestCase;

/**
 * Laravel Boost picks up third-party guidelines from
 * resources/boost/guidelines/core.blade.php and renders them with Blade.
 */
class BoostGuidelinesTest extends TestCase
{
    private const PATH = __DIR__.'/../../resources/boost/guidelines/core.blade.php';

    public function test_the_guidelines_are_where_boost_looks(): void
    {
        $this->assertFileExists(self::PATH);
        $this->assertStringStartsWith('@verbatim', $this->source());
        $this->assertStringEndsWith('@endverbatim', trim($this->source()));
    }

    public function test_the_guidelines_are_static_blade(): void
    {
        // Everything is inside @verbatim, so the examples' {{ }} and <x-…>
        // reach the agent as written instead of being executed.
        $this->assertStringNotContainsString('<?php', Blade::compileString($this->source()));

        $rendered = Blade::render($this->source());

        $this->assertStringStartsWith('## Laravel Comments (ruvelo/laravel-comments)', trim($rendered));
        $this->assertStringContainsString('<x-comments::thread :for="$post" />', $rendered);
        $this->assertStringContainsString('<h1>{{ $post->title }}</h1>', $rendered);
        $this->assertSame(substr_count($rendered, '<code-snippet '), substr_count($rendered, '</code-snippet>'));
        $this->assertLessThan(1200, str_word_count($rendered), 'Guidelines are loaded up front: keep them short.');
    }

    public function test_the_classes_and_namespaces_it_names_exist(): void
    {
        preg_match_all('/Ruvelo\\\\Comments\\\\[A-Za-z\\\\]+/', $this->source(), $matches);

        $this->assertNotEmpty($matches[0]);

        foreach (array_unique($matches[0]) as $class) {
            $namespace = __DIR__.'/../../src/'.str_replace('\\', '/', substr($class, strlen('Ruvelo\\Comments\\')));

            $this->assertTrue(class_exists($class) || interface_exists($class) || trait_exists($class) || is_dir($namespace), "{$class} doesn't exist.");
        }
    }

    public function test_the_config_keys_it_names_exist(): void
    {
        preg_match_all('/`(comments\.[a-z_.]+)`/', $this->source(), $matches);

        $this->assertNotEmpty($matches[1]);

        foreach (array_unique($matches[1]) as $key) {
            $this->assertTrue(config()->has($key), "config('{$key}') doesn't exist.");
        }
    }

    public function test_the_gates_it_names_are_the_ones_the_package_checks(): void
    {
        preg_match_all('/^\| `(comments-[a-z]+)` \|/m', $this->source(), $matches);

        $this->assertSame(['comments-create', 'comments-update', 'comments-delete', 'comments-react', 'comments-moderate'], $matches[1]);

        $source = implode("\n", array_map(fn (string $file): string => (string) file_get_contents($file), $this->phpFiles(__DIR__.'/../../src')));

        foreach ($matches[1] as $gate) {
            $this->assertStringContainsString("'{$gate}'", $source, "The package never checks {$gate}.");
        }
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $directory): array
    {
        $files = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function source(): string
    {
        return (string) file_get_contents(self::PATH);
    }
}
