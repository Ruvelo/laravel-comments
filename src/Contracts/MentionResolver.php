<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Turns the `@handles` in a comment into people. Bind your own class in
 * comments.mentions.resolver to match handles however your app names users.
 */
interface MentionResolver
{
    /**
     * The people behind the handles, keyed by lower-cased handle. Unknown
     * handles are simply left out. Called once per comment, with every
     * handle it contains, so one query is enough.
     *
     * @param  list<string>  $handles  Without the "@".
     * @return array<string, Model>
     */
    public function resolve(array $handles): array;

    /**
     * Where a mention links to, or null to show it as plain text.
     */
    public function url(Model $person): ?string;
}
