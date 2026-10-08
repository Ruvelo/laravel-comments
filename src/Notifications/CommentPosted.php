<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Ruvelo\Comments\Comments;
use Ruvelo\Comments\Models\Comment;
use Ruvelo\Comments\Support\Commentables;

/**
 * Someone commented on your model, or replied to your comment.
 *
 * toArray() returns `title`, `body` and `url`, the shape
 * ruvelo/laravel-inbox shows without any setup.
 */
class CommentPosted extends Notification
{
    use Queueable;

    public function __construct(public readonly Comment $comment) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return array_values(config('comments.notifications.channels', ['database']));
    }

    /**
     * @return array{title: string, body: string, url: string|null, comment_id: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title($notifiable),
            'body' => $this->comment->excerpt(),
            'url' => Comments::url($this->comment),
            'comment_id' => $this->comment->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title($notifiable))
            ->line($this->comment->excerpt(500));

        $url = Comments::url($this->comment);

        return $url === null ? $mail : $mail->action('View the comment', $url);
    }

    protected function title(object $notifiable): string
    {
        $who = $this->comment->authorName();
        $parent = $this->comment->parent;

        if ($parent !== null && $notifiable instanceof Model && $parent->isAuthoredBy($notifiable)) {
            return "{$who} replied to your comment";
        }

        $title = Commentables::call($this->comment->commentable, 'commentTitle');

        return is_string($title) ? "{$who} commented on “{$title}”" : "{$who} commented";
    }
}
