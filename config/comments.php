<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Commentable models
    |--------------------------------------------------------------------------
    |
    | The models people can comment on, keyed by the short name used in URLs
    | and the JSON API (`/comments/threads/post/42`). Aliases in your
    | Relation::morphMap() work too. Requests naming any other type get a
    | 404: class names are never taken from the client.
    |
    */

    'commentables' => [
        // 'post' => App\Models\Post::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    |
    | Every route lives under `path`: the form and JSON endpoints, the
    | moderation page and the permalinks used in notifications. Each one
    | answers JSON when the request asks for it, so the same routes serve
    | Blade forms, Livewire, Inertia and SPAs. The default `web` middleware
    | means the signed-in user is the session's. Set `routes` to false to
    | register your own (copy routes/web.php).
    |
    */

    'routes' => true,

    'path' => env('COMMENTS_PATH', 'comments'),

    'domain' => null,

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Threads
    |--------------------------------------------------------------------------
    |
    | `max_depth` is how many levels a thread shows: 1 is flat, 2 is comments
    | and replies. A reply to the deepest level joins that level instead of
    | nesting further. `per_page` counts top-level comments; their replies
    | always come along. `sort` is the default order: oldest or newest.
    |
    */

    'max_depth' => 2,

    'per_page' => 20,

    'sort' => 'oldest',

    /*
    |--------------------------------------------------------------------------
    | Writing
    |--------------------------------------------------------------------------
    |
    | `max_length` caps a comment in characters. `edit_window` is how many
    | minutes authors may edit their comment after posting; null means
    | always.
    |
    */

    'max_length' => 5000,

    'edit_window' => null,

    /*
    |--------------------------------------------------------------------------
    | Moderation
    |--------------------------------------------------------------------------
    |
    | With `require_approval`, new comments wait in the moderation queue
    | until someone who passes the `comments-moderate` gate approves them.
    | Their author still sees them, marked as waiting. Moderators' own
    | comments are approved straight away.
    |
    */

    'require_approval' => false,

    /*
    |--------------------------------------------------------------------------
    | Spam and abuse
    |--------------------------------------------------------------------------
    |
    | Each author may post `rate_limit.max` comments every
    | `rate_limit.minutes` through the routes (the PHP API is not limited).
    | Set `max` to null to turn it off. `honeypot` names a hidden field that
    | people never fill in; posts that fill it are quietly dropped.
    |
    */

    'rate_limit' => [
        'max' => 5,
        'minutes' => 1,
    ],

    'honeypot' => 'website',

    /*
    |--------------------------------------------------------------------------
    | Reactions
    |--------------------------------------------------------------------------
    |
    | The emoji people can react with, one of each per person. An empty list
    | turns reactions off.
    |
    */

    'reactions' => ['👍', '❤️', '🎉', '😄', '😮', '😢'],

    /*
    |--------------------------------------------------------------------------
    | Mentions
    |--------------------------------------------------------------------------
    |
    | Set a resolver to turn `@handle` into a link to that person. The
    | bundled ColumnResolver looks handles up in one column of your user
    | model and links to `route` when set (plain text otherwise); implement Ruvelo\Comments\Contracts\MentionResolver for
    | anything else. Null turns mentions off.
    |
    */

    'mentions' => [
        'resolver' => null, // Ruvelo\Comments\Mentions\ColumnResolver::class
        'model' => null, // defaults to your `users` auth provider's model
        'column' => 'username',
        'route' => null, // a route taking the user, e.g. 'profile.show'
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Off by default. When on, new comments notify the commentable's owners
    | (through an optional commentNotifiables() method on the model) and,
    | for replies, the parent comment's author. Mentioned people get their
    | own notification. Each is a regular Laravel notification sent through
    | `channels`.
    |
    */

    'notifications' => [
        'enabled' => (bool) env('COMMENTS_NOTIFICATIONS', false),
        'channels' => ['database'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authors
    |--------------------------------------------------------------------------
    |
    | Authors that implement Ruvelo\Comments\Contracts\CommentAuthor choose
    | their own name and avatar. Others show this attribute and initials.
    |
    */

    'author_name_attribute' => 'name',

    /*
    |--------------------------------------------------------------------------
    | Markdown
    |--------------------------------------------------------------------------
    |
    | Extra CommonMark extensions (class names or instances) and options
    | merged over the defaults. Raw HTML is always escaped and unsafe links
    | are always dropped: comments come from anyone.
    |
    */

    'markdown' => [
        'extensions' => [],
        'options' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Moderation page layout
    |--------------------------------------------------------------------------
    |
    | The moderation page uses the package's own layout. To put it inside
    | your app's, name your layout view here; the page fills the section
    | named `layout_section`.
    |
    */

    'layout' => null,

    'layout_section' => 'content',

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Two tables: {prefix}comments and {prefix}comment_reactions. Set
    | `run_migrations` to false if you publish the migration and run it
    | yourself.
    |
    */

    'table_prefix' => '',

    'run_migrations' => true,

];
