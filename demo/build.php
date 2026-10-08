<?php

declare(strict_types=1);

/*
 * Builds the read-only demo published at https://ruvelo.github.io/laravel-comments/.
 *
 * Seeds a comment thread on a fictional Halyard changelog entry in an
 * in-memory database, renders the host page and the moderation page through
 * the package's real routes, component and views, and writes the HTML out as
 * static files. demo.js makes reactions, the preview and sorting work in the
 * browser; posting is read-only.
 *
 *   php demo/build.php <site-dir> [<shots-dir>]
 *
 * <shots-dir>, when given, receives the pages the screenshots are taken of,
 * without the demo banner.
 */

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\Foundation\Application;
use Ruvelo\Comments\Comments;
use Ruvelo\Comments\CommentsServiceProvider;
use Ruvelo\Comments\Concerns\HasComments;
use Ruvelo\Comments\Concerns\IsCommentAuthor;
use Ruvelo\Comments\Contracts\CommentAuthor;
use Ruvelo\Comments\Mentions\ColumnResolver;
use Ruvelo\Comments\Models\Comment;

require __DIR__.'/../vendor/autoload.php';

const HOST = 'https://ruvelo.github.io';
const PATH = 'laravel-comments';
const REPO = 'https://github.com/Ruvelo/laravel-comments';

$site = rtrim($argv[1] ?? __DIR__.'/../build/site', '/');
$shots = isset($argv[2]) ? rtrim($argv[2], '/') : null;

foreach ([
    'APP_ENV' => 'testing',
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'APP_URL' => HOST,
    'APP_NAME' => 'Halyard',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'SESSION_DRIVER' => 'array',
    'COMMENTS_PATH' => PATH.'/comments',
] as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $_SERVER[$key] = $value;
}

class DemoUser extends User implements CommentAuthor
{
    use IsCommentAuthor;

    protected $table = 'users';

    protected $guarded = [];
}

class ChangelogEntry extends Model
{
    use HasComments;

    protected $table = 'entries';

    protected $guarded = [];

    public function commentUrl(): ?string
    {
        return '/'.PATH.'/';
    }
}

$app = Application::create(basePath: null, options: ['extra' => ['providers' => [CommentsServiceProvider::class]]]);
$app['config']->set('auth.providers.users.model', DemoUser::class);
$app['config']->set('comments.commentables', ['changelog' => ChangelogEntry::class]);
$app['config']->set('comments.require_approval', true);
$app['config']->set('comments.mentions.resolver', ColumnResolver::class);
$app['view']->addLocation(__DIR__.'/views');

Gate::define('comments-moderate', fn (DemoUser $user) => $user->name === 'Maya Okafor');

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('username');
    $table->string('email')->unique();
    $table->string('password');
    $table->timestamps();
});
Schema::create('entries', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->timestamps();
});
$app->make(ConsoleKernel::class)->call('migrate', ['--force' => true]);

// --- Seed -------------------------------------------------------------------

$people = [];
foreach (['Maya Okafor', 'Tom Reyes', 'Inès Laurent', 'Kenji Mori', 'Sam Patel', 'Lena Fischer', 'Ravi Shah', 'Ana Costa', 'Jonas Berg'] as $name) {
    $first = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', strtok($name, ' ')));
    $people[strtok($name, ' ')] = DemoUser::forceCreate([
        'name' => $name,
        'username' => $first,
        'email' => $first.'@halyard.test',
        'password' => bin2hex(random_bytes(16)),
    ]);
}

$entry = ChangelogEntry::forceCreate(['title' => 'Usage-based billing is here']);
$now = Carbon::now();

$at = fn (string $ago) => Carbon::setTestNow($now->copy()->modify("-{$ago}"));
$say = function (string $ago, string $who, string $body, ?Comment $parent = null, bool $approve = true) use ($entry, $people, $at): Comment {
    $at($ago);
    $comment = Comments::post($entry, $people[$who], trim($body), $parent);
    if ($approve) {
        Comments::approve($comment);
    }

    return $comment;
};
$react = function (Comment $comment, string $emoji, string ...$who) use ($people): void {
    foreach ($who as $name) {
        Comments::react($comment, $people[$name], $emoji);
    }
};

$tom = $say('2 days', 'Tom', <<<'MD'
This is the one we've been waiting for. Does metering work with our **existing Stripe price IDs**, or do we need to recreate every product?
MD);
$react($tom, '👍', 'Inès', 'Kenji', 'Sam', 'Lena');
$react($tom, '🎉', 'Ravi', 'Jonas');

$maya = $say('47 hours', 'Maya', <<<'MD'
Existing price IDs work. Halyard maps each meter to a price, so you only add a `meter` key:

```json
{ "price": "price_1PqVb2", "meter": "api_calls" }
```
MD, $tom);
$at('46 hours');
Comments::update($maya, $maya->body."\n\nNothing changes for customers on flat plans.");
$react($maya, '❤️', 'Tom', 'Inès', 'Kenji');

$say('45 hours', 'Tom', 'Perfect, thanks @maya. Migrating our first plan this afternoon.', $tom);

$ines = $say('1 day', 'Inès', <<<'MD'
We moved two customers over this morning. The invoice preview showing *projected* usage is a lovely touch.

One request: could the CSV export include the meter name? Finance asked within the hour.
MD);
$react($ines, '👍', 'Tom', 'Sam', 'Lena');
$react($ines, '😄', 'Maya');
$say('22 hours', 'Kenji', 'Seconding this. Also, is there a way to backfill usage from before the switch?', $ines);

$deleted = $say('20 hours', 'Sam', 'Do events get rounded per event or per invoice line? Our totals are off by a cent.');
$say('19 hours', 'Kenji', 'Per invoice line, so fractions only round once. The [rounding notes](https://example.com/docs/rounding) have a worked example.', $deleted);
$at('18 hours');
Comments::delete($deleted, $people['Sam']);

$kenji = $say('3 hours', 'Kenji', <<<'MD'
Heads-up for anyone on the API: `GET /v2/usage` returns timestamps in **UTC**. Our dashboard was a day off until I noticed:

```php
$events = Halyard::usage()->since(now('UTC')->startOfMonth());
```
MD);
$react($kenji, '😮', 'Tom', 'Lena');
$react($kenji, '👍', 'Inès');

$closing = $say('1 hour', 'Maya', <<<'MD'
Thanks, everyone. Meter names in the CSV export ship next week. Backfill is in beta: reply here and I'll switch it on for your account.
MD);
$react($closing, '🎉', 'Tom', 'Inès', 'Kenji', 'Sam', 'Jonas');
$react($closing, '❤️', 'Lena', 'Ravi');

// Waiting for a moderator.
$say('40 minutes', 'Ravi', 'Is there an annual discount for usage-based plans, or is it monthly only?', approve: false);
$say('25 minutes', 'Lena', 'We need the meter name in the export too. +1 to @ines.', $ines, approve: false);
$say('10 minutes', 'Ana', 'Cheap API credits!!! 90% off at cheap-credits.example, limited time 💰', approve: false);
Carbon::setTestNow();

// --- Render -----------------------------------------------------------------

Route::middleware('web')->get(PATH, fn (Request $request) => view('changelog', [
    'entry' => $entry,
    'compact' => $request->boolean('compact'),
]));

$http = $app->make(HttpKernel::class);

$fetch = function (string $path, ?User $as = null) use ($app, $http): string {
    $app['auth']->forgetGuards();
    if ($as !== null) {
        $app['auth']->guard()->setUser($as);
    }

    $request = Request::create(HOST.'/'.PATH.($path === '' ? '' : '/'.$path));
    $response = $http->handle($request);
    $http->terminate($request, $response);

    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException("/{$path} answered {$response->getStatusCode()}");
    }

    // Root-relative links, so the same files work on Pages and on localhost.
    return str_replace(HOST.'/', '/', $response->getContent());
};

$banner = '<div style="background:#3d4eff;color:#fff;font:500 .875rem/1.4 &quot;Geist&quot;,ui-sans-serif,system-ui,sans-serif;padding:.55rem 16px;text-align:center">'
    .'You’re looking at a read-only demo of <a href="'.REPO.'" style="color:inherit;font-weight:700">ruvelo/laravel-comments</a>. '
    .'Reactions, sorting and the preview work; install it to post. '
    .'<a href="'.REPO.'#readme" style="color:inherit">Read the docs</a></div>';

$note = '<p class="comments-note">This is a read-only demo, so nothing you write is saved. Try the Preview tab, react to a comment, or open a reply.</p>';

$write = function (string $root, string $path, string $html, bool $demo = true) use ($banner, $note): void {
    if ($demo) {
        $html = preg_replace('/<body>/', '<body>'.$banner, $html, 1);
        // The note goes under the compose box.
        $html = preg_replace('#(<form [^>]*id="new".*?)(</form>)#s', '$1'.$note.'$2', $html, 1);
        $html = str_replace('</body>', '<script>window.COMMENTS_DEMO = { viewer: "Kenji Mori" };</script><script>'.file_get_contents(__DIR__.'/demo.js').'</script></body>', $html);
    }
    $file = $root.'/'.($path === '' ? '' : $path.'/').'index.html';
    is_dir(dirname($file)) || mkdir(dirname($file), 0777, true);
    file_put_contents($file, $html);
};

$pages = [
    '' => $fetch('', $people['Kenji']),
    'comments/moderation' => $fetch('comments/moderation', $people['Maya']),
    'comments/moderation/approved' => $fetch('comments/moderation/approved', $people['Maya']),
    'comments/moderation/deleted' => $fetch('comments/moderation/deleted', $people['Maya']),
];

foreach ($pages as $path => $html) {
    $write($site, $path, $html);
}

if ($shots !== null) {
    foreach ([
        'page' => $fetch('', $people['Kenji']),
        'thread' => $fetch('?compact=1', $people['Kenji']),
        'reply' => $fetch('?compact=1&comments_reply='.$tom->id, $people['Kenji']),
        'moderation' => $pages['comments/moderation'],
    ] as $name => $html) {
        $write($shots, $name, $html, false);
    }
}

fwrite(STDOUT, sprintf("Built %d demo pages (%d comments) into %s\n", count($pages), Comment::withTrashed()->count(), $site));
