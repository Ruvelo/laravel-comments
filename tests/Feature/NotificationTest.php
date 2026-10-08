<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Tests\Feature;

use Illuminate\Support\Facades\Notification;
use Ruvelo\Comments\Comments;
use Ruvelo\Comments\Mentions\ColumnResolver;
use Ruvelo\Comments\Notifications\CommentPosted;
use Ruvelo\Comments\Notifications\MentionedInComment;
use Ruvelo\Comments\Tests\TestCase;

class NotificationTest extends TestCase
{
    public function test_notifications_are_off_by_default(): void
    {
        Notification::fake();
        $owner = $this->user('Owner');

        $this->comment($this->newPost($owner), $this->user(), 'Hi');

        Notification::assertNothingSent();
    }

    public function test_the_owner_hears_about_new_comments(): void
    {
        config(['comments.notifications.enabled' => true]);
        $owner = $this->user('Olivia Owner');
        $post = $this->newPost($owner, ['title' => 'Usage-based billing']);

        $comment = $this->comment($post, $this->user('Ada Lovelace'), 'Great **news**');

        $notification = $owner->notifications()->sole();
        $this->assertSame(CommentPosted::class, $notification->type);
        $this->assertSame([
            'title' => 'Ada Lovelace commented on “Usage-based billing”',
            'body' => 'Great news',
            'url' => 'http://localhost/comments/'.$comment->id,
            'comment_id' => $comment->id,
        ], $notification->data);
    }

    public function test_replies_notify_the_parent_author_but_never_the_replier(): void
    {
        Notification::fake();
        config(['comments.notifications.enabled' => true]);
        $owner = $this->user('Owner');
        $ada = $this->user();
        $post = $this->newPost($owner);
        $parent = $this->comment($post, $ada, 'Question?');

        $this->comment($post, $owner, 'Answer.', $parent);

        Notification::assertSentTo($ada, CommentPosted::class, function (CommentPosted $notification) use ($ada) {
            return $notification->toArray($ada)['title'] === 'Owner replied to your comment';
        });
        Notification::assertSentTimes(CommentPosted::class, 2); // the owner on Ada's comment, Ada on the reply
        Notification::assertSentToTimes($owner, CommentPosted::class, 1);
    }

    public function test_mentions_notify_once_and_replace_the_generic_notification(): void
    {
        Notification::fake();
        config(['comments.notifications.enabled' => true, 'comments.mentions.resolver' => ColumnResolver::class]);
        $owner = $this->user('Olivia Owner');
        $tom = $this->user('Tom Reyes');
        $ada = $this->user();

        $comment = $this->comment($this->newPost($owner), $ada, 'Hey @olivia and @tom and @ada and @tom');

        Notification::assertSentToTimes($owner, MentionedInComment::class, 1);
        Notification::assertSentToTimes($tom, MentionedInComment::class, 1);
        Notification::assertNotSentTo($owner, CommentPosted::class);
        Notification::assertNotSentTo($ada, MentionedInComment::class);
        Notification::assertSentTo($tom, MentionedInComment::class, fn (MentionedInComment $n) => $n->toArray($tom)['title'] === 'Ada Lovelace mentioned you on “Release notes”');

        // Editing in a new mention tells only the new person.
        $cy = $this->user('Cy Mori');
        Comments::update($comment, 'Hey @olivia and @tom and @cy');
        Notification::assertSentToTimes($tom, MentionedInComment::class, 1);
        Notification::assertSentToTimes($cy, MentionedInComment::class, 1);
    }

    public function test_pending_comments_notify_when_approved(): void
    {
        Notification::fake();
        config(['comments.notifications.enabled' => true, 'comments.require_approval' => true]);
        $owner = $this->user('Owner');

        $comment = $this->comment($this->newPost($owner), $this->user(), 'Hi');
        Notification::assertNothingSent();

        Comments::approve($comment);
        Notification::assertSentTo($owner, CommentPosted::class);
    }

    public function test_mail_is_available_as_a_channel(): void
    {
        $owner = $this->user('Owner');
        $comment = $this->comment($this->newPost($owner), $this->user(), 'Hi');

        $mail = (new CommentPosted($comment))->toMail($owner);

        $this->assertSame('Ada Lovelace commented on “Release notes”', $mail->subject);
        $this->assertSame('http://localhost/comments/'.$comment->id, $mail->actionUrl);
    }
}
