# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0] - 2026-10-08

First release.

### Added

- Comments on any Eloquent model through the `HasComments` trait, by any model as the author. An optional `CommentAuthor` contract (and `IsCommentAuthor` trait) for names and avatars; initials otherwise.
- Threaded replies with a configurable `max_depth`; deeper replies join the deepest allowed level.
- Safe Markdown: raw HTML escaped, unsafe links dropped, outside links `nofollow`, images shown as links.
- Optional `@mentions` through a `MentionResolver`, with a bundled `ColumnResolver`.
- Reactions from a configurable emoji set, one of each per person, with who reacted on hover.
- Edit markers, an optional edit window, and soft deletes that keep replies under a "This comment was deleted" placeholder.
- Optional approval queue and a moderation page with waiting, approved and deleted tabs.
- Gates with sensible defaults: `comments-create`, `comments-update`, `comments-delete`, `comments-react` and `comments-moderate`.
- Per-author rate limiting, a honeypot field and a length limit.
- Opt-in `CommentPosted` and `MentionedInComment` notifications, with `title`, `body` and `url` for ruvelo/laravel-inbox.
- `<x-comments::thread :for="$post" />`: works as plain HTML forms, enhanced by a small inline script (posting, editing, deleting, reactions and previews in place).
- JSON answers on every route for SPAs and Inertia, with an allow-list of commentable types.
- PHP API: `Comments::post()`, `update()`, `delete()`, `react()`, `approve()`, `for()`, `thread()`, `countFor()`, `render()` and `allows()`; a `withCommentsCount()` scope.
- `CommentPosted`, `CommentUpdated`, `CommentDeleted`, `CommentApproved` and `ReactionToggled` events; typed exceptions extending `CommentsException`.
- `Comment::factory()` for tests in host apps.
- Ruvelo house style UI: light and dark, scoped styles, themable through CSS variables.

[Unreleased]: https://github.com/Ruvelo/laravel-comments/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/Ruvelo/laravel-comments/releases/tag/v1.0.0
