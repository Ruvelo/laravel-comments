<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Ruvelo\Comments\Comments;
use Ruvelo\Comments\CommentsServiceProvider;
use Ruvelo\Comments\Markdown\Renderer;
use Ruvelo\Comments\Models\Comment;
use Ruvelo\Comments\Tests\Fixtures\Post;
use Ruvelo\Comments\Tests\Fixtures\User;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [CommentsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('c', 32)));
        $app['config']->set('app.url', 'https://app.test');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('comments.commentables', ['post' => Post::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->nullable();
            $table->string('email')->unique();
            $table->string('avatar')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->boolean('closed')->default(false);
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // The page the thread lives on, for redirects back.
        Route::middleware('web')->get('/posts/{post}', fn (Post $post) => view('thread-page', ['post' => $post]));
    }

    protected function setUp(): void
    {
        parent::setUp();

        app('view')->addLocation(__DIR__.'/Fixtures/views');
        app(Renderer::class)->reset();
    }

    protected function user(string $name = 'Ada Lovelace', array $attributes = []): User
    {
        $first = strtolower(explode(' ', $name)[0]);

        return User::forceCreate([
            'name' => $name,
            'username' => $first,
            'email' => $first.'@example.com',
            'password' => 'secret',
            ...$attributes,
        ]);
    }

    protected function newPost(?User $owner = null, array $attributes = []): Post
    {
        return Post::forceCreate(['title' => 'Release notes', 'user_id' => $owner?->id, ...$attributes]);
    }

    protected function comment(Post $post, User $author, string $body = 'Hello', ?Comment $parent = null): Comment
    {
        return Comments::post($post, $author, $body, $parent);
    }

    protected function moderators(string ...$names): void
    {
        Gate::define('comments-moderate', fn (User $user) => in_array($user->name, $names, true));
    }
}
