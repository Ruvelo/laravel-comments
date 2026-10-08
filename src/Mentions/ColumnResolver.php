<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Mentions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Ruvelo\Comments\Comments;
use Ruvelo\Comments\Contracts\MentionResolver;

/**
 * Matches `@handle` against one column of your user model
 * (comments.mentions.column, `username` by default), ignoring case, and
 * links to comments.mentions.route when it's set.
 */
class ColumnResolver implements MentionResolver
{
    public function resolve(array $handles): array
    {
        if ($handles === []) {
            return [];
        }

        $class = config('comments.mentions.model') ?? Comments::userModel();
        $column = (string) config('comments.mentions.column', 'username');

        /** @var Model $model */
        $model = new $class;

        // whereLike() ignores case on every database; "_" is a wildcard in
        // LIKE, so the exact match is checked again below.
        $people = [];
        $model->newQuery()
            ->where(function ($query) use ($column, $handles): void {
                foreach ($handles as $handle) {
                    $query->orWhereLike($column, $handle);
                }
            })
            ->limit(50)
            ->get()
            ->each(function (Model $person) use ($column, $handles, &$people): void {
                $handle = $person->getAttribute($column);
                if (is_string($handle) && in_array(strtolower($handle), $handles, true)) {
                    $people[strtolower($handle)] = $person;
                }
            });

        return $people;
    }

    public function url(Model $person): ?string
    {
        $route = config('comments.mentions.route');

        return is_string($route) && Route::has($route) ? route($route, $person) : null;
    }
}
