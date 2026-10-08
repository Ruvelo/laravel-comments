<?php

declare(strict_types=1);

namespace Ruvelo\Comments;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Ruvelo\Comments\Markdown\Renderer;

class CommentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/comments.php', 'comments');

        $this->app->singleton(Renderer::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'comments');

        // <x-comments::thread :for="$post" />
        Blade::componentNamespace('Ruvelo\\Comments\\View\\Components', 'comments');

        if (config('comments.routes', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        if (config('comments.run_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/comments.php' => config_path('comments.php'),
            ], 'comments-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/comments'),
            ], 'comments-views');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'comments-migrations');
        }
    }
}
