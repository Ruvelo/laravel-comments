<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Tests\Feature;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Ruvelo\Comments\Comments;
use Ruvelo\Comments\Events\CommentApproved;
use Ruvelo\Comments\Events\CommentDeleted;
use Ruvelo\Comments\Events\CommentPosted;
use Ruvelo\Comments\Events\CommentUpdated;
use Ruvelo\Comments\Events\ReactionToggled;
use Ruvelo\Comments\Exceptions\CommentingClosed;
use Ruvelo\Comments\Exceptions\CommentsException;
use Ruvelo\Comments\Exceptions\InvalidComment;
use Ruvelo\Comments\Exceptions\InvalidParent;
use Ruvelo\Comments\Exceptions\InvalidReaction;
use Ruvelo\Comments\Exceptions\NotCommentable;
use Ruvelo\Comments\Models\Comment;
use Ruvelo\Comments\Models\Reaction;
use Ruvelo\Comments\Tests\Fixtures\User;
use Ruvelo\Comments\Tests\TestCase;

class PostingTest extends TestCase
{
    public function test_posting_a_comment_renders_it_and_fires_an_event(): void
    {
        Event::fake([CommentPosted::class]);
        $post = $this->newPost();
        $ada = $this->user();

        $comment = Comments::post($post, $ada, "  Ship **it**\r\n  ");

        $this->assertSame('Ship **it**', $comment->body);
        $this->assertSame("<p>Ship <strong>it</strong></p>\n", $comment->html);
        $this->assertTrue($comment->isApproved());
        $this->assertTrue($comment->isAuthoredBy($ada));
        $this->assertSame(0, $comment->depth);
        $this->assertNull($comment->root_id);
        $this->assertTrue($comment->commentable->is($post));
        Event::assertDispatched(CommentPosted::class, fn (CommentPosted $event) => $event->comment->is($comment));
    }

    public function test_replies_nest_up_to_the_max_depth_then_join_the_deepest_level(): void
    {
        $post = $this->newPost();
        $ada = $this->user();

        $top = Comments::post($post, $ada, 'Top');
        $reply = Comments::post($post, $ada, 'Reply', $top);
        $deeper = Comments::post($post, $ada, 'Reply to the reply', $reply->id);

        $this->assertSame([1, $top->id, $top->id], [$reply->depth, $reply->parent_id, $reply->root_id]);
        // Default max_depth is 2: comments and replies. Deeper joins the reply level.
        $this->assertSame([1, $top->id, $top->id], [$deeper->depth, $deeper->parent_id, $deeper->root_id]);
    }

    public function test_a_deeper_max_depth_allows_more_levels(): void
    {
        config(['comments.max_depth' => 3]);
        $post = $this->newPost();
        $ada = $this->user();

        $top = Comments::post($post, $ada, 'Top');
        $reply = Comments::post($post, $ada, 'Reply', $top);
        $third = Comments::post($post, $ada, 'Third', $reply);
        $fourth = Comments::post($post, $ada, 'Fourth', $third);

        $this->assertSame([2, $reply->id, $top->id], [$third->depth, $third->parent_id, $third->root_id]);
        $this->assertSame([2, $reply->id, $top->id], [$fourth->depth, $fourth->parent_id, $fourth->root_id]);
    }

    public function test_a_max_depth_of_one_keeps_the_thread_flat(): void
    {
        config(['comments.max_depth' => 1]);
        $post = $this->newPost();
        $ada = $this->user();

        $reply = Comments::post($post, $ada, 'Reply', Comments::post($post, $ada, 'Top'));

        $this->assertNull($reply->parent_id);
        $this->assertSame(0, $reply->depth);
    }

    public function test_replies_must_hang_from_a_live_published_comment_on_the_same_model(): void
    {
        $post = $this->newPost();
        $other = $this->newPost();
        $ada = $this->user();

        $elsewhere = Comments::post($other, $ada, 'Elsewhere');
        $deleted = Comments::post($post, $ada, 'Gone');
        Comments::delete($deleted);
        config(['comments.require_approval' => true]);
        $pending = Comments::post($post, $ada, 'Waiting');

        foreach ([$elsewhere, $deleted, $pending, 999] as $parent) {
            try {
                Comments::post($post, $ada, 'Reply', $parent);
                $this->fail('Expected InvalidParent');
            } catch (InvalidParent $e) {
                $this->assertInstanceOf(CommentsException::class, $e);
            }
        }
    }

    public function test_empty_and_overlong_comments_are_refused(): void
    {
        config(['comments.max_length' => 10]);
        $post = $this->newPost();
        $ada = $this->user();

        try {
            Comments::post($post, $ada, "  \n ");
            $this->fail('Expected InvalidComment');
        } catch (InvalidComment $e) {
            $this->assertSame('Write something before posting.', $e->getMessage());
        }

        $this->expectException(InvalidComment::class);
        $this->expectExceptionMessage('Keep comments under 10 characters.');
        Comments::post($post, $ada, str_repeat('é', 11));
    }

    public function test_closed_threads_refuse_new_comments(): void
    {
        $this->expectException(CommentingClosed::class);

        Comments::post($this->newPost(attributes: ['closed' => true]), $this->user(), 'Hi');
    }

    public function test_models_without_the_trait_cannot_be_commented_on(): void
    {
        $this->expectException(NotCommentable::class);

        Comments::post($this->user('Bob'), $this->user(), 'Hi');
    }

    public function test_editing_marks_the_comment_edited_and_fires_an_event(): void
    {
        Event::fake([CommentUpdated::class]);
        $comment = Comments::post($this->newPost(), $this->user(), 'First');

        Comments::update($comment, 'First');
        $this->assertNull($comment->edited_at);
        Event::assertNotDispatched(CommentUpdated::class);

        Carbon::setTestNow('2026-10-08 12:00:00');
        Comments::update($comment, 'Second *take*');

        $this->assertSame('Second *take*', $comment->fresh()->body);
        $this->assertStringContainsString('<em>take</em>', $comment->fresh()->html);
        $this->assertSame('2026-10-08 12:00:00', $comment->fresh()->edited_at->toDateTimeString());
        Event::assertDispatched(CommentUpdated::class);
    }

    public function test_deleting_is_soft_and_fires_an_event_once(): void
    {
        Event::fake([CommentDeleted::class]);
        $ada = $this->user();
        $comment = Comments::post($this->newPost(), $ada, 'Oops');

        Comments::delete($comment, $ada);
        Comments::delete($comment, $ada);

        $this->assertSoftDeleted($comment);
        Event::assertDispatchedTimes(CommentDeleted::class, 1);
        Event::assertDispatched(CommentDeleted::class, fn (CommentDeleted $event) => $event->by?->is($ada) === true);
    }

    public function test_reactions_toggle_and_stay_one_per_person_per_emoji(): void
    {
        Event::fake([ReactionToggled::class]);
        $comment = Comments::post($this->newPost(), $this->user(), 'Nice');
        $bob = $this->user('Bob Reyes');
        $cy = $this->user('Cy Mori');

        $this->assertTrue(Comments::react($comment, $bob, '🎉'));
        $this->assertTrue(Comments::react($comment, $bob, '👍'));
        $this->assertTrue(Comments::react($comment, $cy, '🎉'));
        $this->assertSame(3, Reaction::count());

        $this->assertFalse(Comments::react($comment, $bob, '🎉'));
        $this->assertSame(2, Reaction::count());

        $summary = $comment->load('reactions.reactor')->reactionSummary($cy);
        $this->assertSame([
            ['emoji' => '👍', 'count' => 1, 'reacted' => false, 'names' => ['Bob Reyes']],
            ['emoji' => '🎉', 'count' => 1, 'reacted' => true, 'names' => ['Cy Mori']],
        ], $summary);

        Event::assertDispatchedTimes(ReactionToggled::class, 4);
        Event::assertDispatched(ReactionToggled::class, fn (ReactionToggled $event) => ! $event->added && $event->emoji === '🎉');
    }

    public function test_the_database_refuses_duplicate_reactions(): void
    {
        $comment = Comments::post($this->newPost(), $this->user(), 'Nice');
        $bob = $this->user('Bob Reyes');
        Comments::react($comment, $bob, '🎉');

        $this->expectException(UniqueConstraintViolationException::class);

        $duplicate = new Reaction(['emoji' => '🎉']);
        $duplicate->comment()->associate($comment);
        $duplicate->reactor()->associate($bob);
        $duplicate->save();
    }

    public function test_only_configured_reactions_are_accepted(): void
    {
        config(['comments.reactions' => ['👍']]);
        $comment = Comments::post($this->newPost(), $this->user(), 'Nice');

        $this->expectException(InvalidReaction::class);

        Comments::react($comment, $this->user('Bob'), '🎉');
    }

    public function test_approving_publishes_a_pending_comment(): void
    {
        Event::fake([CommentApproved::class]);
        config(['comments.require_approval' => true]);
        $comment = Comments::post($this->newPost(), $this->user(), 'Hello');

        $this->assertTrue($comment->isPending());

        Comments::approve($comment);
        Comments::approve($comment);

        $this->assertTrue($comment->fresh()->isApproved());
        Event::assertDispatchedTimes(CommentApproved::class, 1);
    }

    public function test_for_count_and_the_count_scope_only_include_published_comments(): void
    {
        $post = $this->newPost();
        $ada = $this->user();
        Comments::post($post, $ada, 'One');
        Comments::delete(Comments::post($post, $ada, 'Deleted'));
        config(['comments.require_approval' => true]);
        Comments::post($post, $ada, 'Pending');

        $this->assertSame(['One'], Comments::for($post)->pluck('body')->all());
        $this->assertSame(1, Comments::countFor($post));
        $this->assertSame(1, $post->newQuery()->withCommentsCount()->first()?->getAttribute('comments_count'));
        $this->assertSame(3, $post->comments()->withTrashed()->count());
    }

    public function test_the_factory_builds_threads_for_host_app_tests(): void
    {
        $post = $this->newPost();
        $ada = $this->user();

        $top = Comment::factory()->on($post)->by($ada)->body('**Hi**')->create();
        $reply = Comment::factory()->replyTo($top)->by($ada)->pending()->edited()->create();

        $this->assertSame("<p><strong>Hi</strong></p>\n", $top->html);
        $this->assertSame([$top->id, 1], [$reply->parent_id, $reply->depth]);
        $this->assertTrue($reply->isPending());
        $this->assertTrue($reply->isEdited());
        $this->assertInstanceOf(User::class, $reply->author);
    }

    public function test_by_default_nobody_moderates_and_signed_in_people_comment(): void
    {
        $ada = $this->user();
        $post = $this->newPost();

        $this->assertFalse(Comments::canModerate($ada));
        $this->assertTrue(Comments::allows('comments-create', $ada, $post));
        $this->assertFalse(Comments::allows('comments-create', null, $post));
        $this->assertFalse(Comments::allows('comments-anything-else', $ada));
    }

    public function test_initials_and_names_fall_back_sensibly(): void
    {
        $this->assertSame('ML', Comment::initialsOf('Maya Lin Okafor'));
        $this->assertSame('K', Comment::initialsOf('kenji'));
        $this->assertSame('Deleted user', Comment::nameOf(null));
    }
}
