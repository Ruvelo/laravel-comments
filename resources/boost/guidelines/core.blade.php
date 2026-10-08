@verbatim
## Laravel Comments (ruvelo/laravel-comments)

Threaded comments on any Eloquent model: Markdown (raw HTML always escaped), replies, reactions, mentions, moderation, rate limiting and optional notifications. One Blade component for server-rendered pages, and the same routes answer JSON for Inertia and SPAs.

### Setup

Add `HasComments` to the model, and list it under a short name in `config/comments.php` (publish with `php artisan vendor:publish --tag=comments-config`). The short name is what URLs and the JSON API use:

<code-snippet name="Making a model commentable" lang="php">
use Ruvelo\Comments\Concerns\HasComments;

class Post extends Model
{
    use HasComments;

    // Optional hooks: where the post lives, who hears about new comments, whether the thread is open.
    public function commentUrl(): ?string
    {
        return route('posts.show', $this);
    }

    public function commentNotifiables(): iterable
    {
        return [$this->author];
    }
}

// config/comments.php
'commentables' => [
    'post' => App\Models\Post::class,
],
</code-snippet>

Aliases from `Relation::morphMap()` / `enforceMorphMap()` work too. Other hooks: `commentsAreOpen(): bool` (false closes the thread, comments stay visible) and `commentTitle(): string`.

Then show the thread on the model's page. It brings its own scoped styles and script; guests see the thread and a sign-in link:

<code-snippet name="The thread component" lang="blade">
<article>
    <h1>{{ $post->title }}</h1>

    <x-comments::thread :for="$post" />
</article>
</code-snippet>

Options: `sort="newest"`, `:per-page="10"`, `data-theme="light"` for light-only apps.

### Permissions

Every write through the routes goes through a gate. Define one with the same name (in `AppServiceProvider::boot()`) to override the default:

| Gate | Arguments | Default |
|---|---|---|
| `comments-create` | `$user, $commentable` | Any signed-in user |
| `comments-update` | `$user, $comment` | The author, within `edit_window` |
| `comments-delete` | `$user, $comment` | The author, or a moderator |
| `comments-react` | `$user, $comment` | Any signed-in user, on published comments |
| `comments-moderate` | `$user` | Nobody, until you define it |

<code-snippet name="Defining gates" lang="php">
use Illuminate\Support\Facades\Gate;

Gate::define('comments-moderate', fn ($user) => $user->is_admin);
Gate::define('comments-create', fn ($user, $post) => $user->hasVerifiedEmail());
</code-snippet>

Define `comments-moderate` whenever `require_approval` is on, or nobody can approve: the queue is at `/comments/moderation`.

### PHP API

`Ruvelo\Comments\Comments` is the one write path (the routes use it too) and fires the events. It does not check gates or rate limits, so call `Comments::authorize('comments-update', $user, $comment)` (throws `NotAllowed`) or `Comments::allows(...)` when acting for a user.

<code-snippet name="PHP API" lang="php">
use Ruvelo\Comments\Comments;

$comment = Comments::post($post, $user, 'Ship **it**');
Comments::post($post, $other, 'Thanks @maya', parent: $comment);
Comments::update($comment, 'Ship **it** on Friday');
Comments::delete($comment, by: $user);       // soft: replies stay
Comments::react($comment, $user, '🎉');      // toggles; true when added
Comments::approve($comment);

Comments::for($post)->latest()->limit(5)->get();   // published only
Comments::countFor($post);
Post::withCommentsCount()->get();                  // comments_count without N+1
Comments::render($markdown);                       // the same safe HTML comments get
</code-snippet>

### JSON API (Inertia, React, Vue)

Send `Accept: application/json`; the routes use the session (`web`) by default, so send the CSRF token on writes. `{type}` is the short name from `commentables`, never a class name.

- `GET /comments/threads/{type}/{id}?sort=&page=&per_page=`: top-level comments with nested `replies`; `meta` has `comments_count`, `can_comment`, `reactions`, `max_depth`
- `POST /comments/threads/{type}/{id}` with `{body, parent_id?}` → `201`
- `PATCH /comments/{id}` `{body}`, `DELETE /comments/{id}`, `POST /comments/{id}/reactions` `{emoji}`, `POST /comments/preview` `{body}` → `{html}`
- `GET /comments/moderation/{status?}`, `POST /comments/{id}/approve` for moderators

Each comment has `id`, `parent_id`, `depth`, `author` (`name`, `initials`, `avatar_url`), `body`, `html` (already escaped, safe to render as HTML), `created_at`, `edited_at`, `approved`, `deleted`, `url`, `reactions`, `can` (`reply`, `update`, `delete`, `react`) and `replies`. Errors: `401` guest, `403` gate or closed thread, `404` unknown type or comment, `422` with `errors.body`, `429` rate limited.

### Notifications

Off by default: set `COMMENTS_NOTIFICATIONS=true` (`comments.notifications.enabled`). The commentable's `commentNotifiables()`, the parent comment's author and mentioned people are notified through `comments.notifications.channels` (default `['database']`). `toArray()` returns `title`, `body` and `url`, so with `ruvelo/laravel-inbox` installed they show up in its bell and inbox with no extra code. Mentions need `comments.mentions.resolver` (e.g. `Ruvelo\Comments\Mentions\ColumnResolver::class`).

### Events

In `Ruvelo\Comments\Events`, each with `$comment`: `CommentPosted` (approved or not: check `$comment->isApproved()`), `CommentUpdated`, `CommentDeleted` (`$by`), `CommentApproved`, `ReactionToggled` (`$reactor`, `$emoji`, `$added`).

### Tests

<code-snippet name="Factory for host-app tests" lang="php">
use Ruvelo\Comments\Models\Comment;

Comment::factory()->on($post)->by($user)->create();
Comment::factory()->on($post)->replyTo($comment)->by($other)->pending()->create();
Comment::factory()->on($post)->by($user)->body('**Hi**')->edited()->create();
</code-snippet>

### Don't

- Never accept a model class name from the client (a `commentable_type` field, a hidden input): register the model under `commentables` or the morph map and use the short name. Unlisted types are a `404`.
- Tables are `ruvelo_comments` and `ruvelo_comment_reactions` (`comments.table_prefix`, default `ruvelo_`), clear of an app's own `comments` table. Don't create a `comments` migration for this package, don't name the tables by hand, and use `$post->comments()` or `Comments::for($post)` rather than `DB::table('comments')`.
- Don't render `body` unescaped: use `html` from the API or `Comments::render()`.
- Don't add your own posting endpoint or rate limiter: post through the routes or `Comments::post()`, and tune `comments.rate_limit` instead.
- To restyle, override the `--comments-*` CSS variables on `.comments`, or publish the views (`php artisan vendor:publish --tag=comments-views`).
@endverbatim
