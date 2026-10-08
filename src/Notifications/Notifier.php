<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Ruvelo\Comments\Models\Comment;
use Ruvelo\Comments\Support\Commentables;

/**
 * Decides who hears about a published comment. Off unless
 * comments.notifications.enabled is true.
 *
 * Mentioned people get a MentionedInComment notification; the
 * commentable's commentNotifiables() and, for replies, the parent's
 * author get CommentPosted. Nobody is told twice, and nobody is told
 * about their own comment.
 *
 * @internal
 */
final class Notifier
{
    /**
     * @param  list<Model>  $mentions
     */
    public static function published(Comment $comment, array $mentions): void
    {
        if (! self::enabled()) {
            return;
        }

        $mentioned = self::mentioned($comment, $mentions);

        $recipients = [];
        foreach (Commentables::call($comment->commentable, 'commentNotifiables', []) as $notifiable) {
            $recipients[] = $notifiable;
        }

        if ($comment->parent_id !== null) {
            $recipients[] = $comment->parent?->author;
        }

        $recipients = self::unique($recipients, $comment, $mentioned);

        if ($recipients !== []) {
            Notification::send($recipients, new CommentPosted($comment));
        }
    }

    /**
     * Tell the people a comment mentions. Returns their keys.
     *
     * @param  list<Model>  $mentions
     * @return list<string>
     */
    public static function mentioned(Comment $comment, array $mentions): array
    {
        if (! self::enabled()) {
            return [];
        }

        $people = self::unique($mentions, $comment);

        if ($people !== []) {
            Notification::send($people, new MentionedInComment($comment));
        }

        return array_map(self::key(...), $people);
    }

    private static function enabled(): bool
    {
        return (bool) config('comments.notifications.enabled', false);
    }

    /**
     * Notifiable models only, without duplicates, the comment's author, or
     * anyone in $skip.
     *
     * @param  iterable<int, mixed>  $people
     * @param  list<string>  $skip
     * @return list<Model>
     */
    private static function unique(iterable $people, Comment $comment, array $skip = []): array
    {
        $unique = [];

        foreach ($people as $person) {
            if (! $person instanceof Model || ! method_exists($person, 'routeNotificationFor') || $comment->isAuthoredBy($person)) {
                continue;
            }

            $key = self::key($person);
            if (! in_array($key, $skip, true)) {
                $unique[$key] = $person;
            }
        }

        return array_values($unique);
    }

    private static function key(Model $person): string
    {
        return $person->getMorphClass().':'.$person->getKey();
    }
}
