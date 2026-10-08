<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Notifications;

use Ruvelo\Comments\Support\Commentables;

/**
 * Someone @mentioned you in a comment. Same shape as CommentPosted.
 */
class MentionedInComment extends CommentPosted
{
    protected function title(object $notifiable): string
    {
        $who = $this->comment->authorName();
        $title = Commentables::call($this->comment->commentable, 'commentTitle');

        return is_string($title) ? "{$who} mentioned you on “{$title}”" : "{$who} mentioned you";
    }
}
