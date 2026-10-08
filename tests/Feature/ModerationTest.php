<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Ruvelo\Comments\Comments;
use Ruvelo\Comments\Events\CommentApproved;
use Ruvelo\Comments\Models\Comment;
use Ruvelo\Comments\Tests\TestCase;

class ModerationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['comments.require_approval' => true]);
        $this->moderators('Mod');
    }

    public function test_new_comments_wait_unless_a_moderator_posts_them(): void
    {
        $post = $this->newPost();

        $this->actingAs($this->user())->postJson("/comments/threads/post/{$post->id}", ['body' => 'Hi'])
            ->assertCreated()
            ->assertJsonPath('data.approved', false)
            ->assertJsonPath('message', 'Thanks! Your comment will appear once a moderator approves it.')
            ->assertJsonPath('comments_count', 0);

        $this->actingAs($this->user('Mod'))->postJson("/comments/threads/post/{$post->id}", ['body' => 'Official'])
            ->assertCreated()
            ->assertJsonPath('data.approved', true);
    }

    public function test_the_moderation_page_is_for_moderators_only(): void
    {
        $this->get('/comments/moderation')->assertForbidden();
        $this->getJson('/comments/moderation')->assertUnauthorized();
        $this->actingAs($this->user())->get('/comments/moderation')->assertForbidden();

        $this->app['auth']->forgetGuards();
        Route::get('/login', fn () => 'Sign in')->name('login');
        app('router')->getRoutes()->refreshNameLookups();
        $this->get('/comments/moderation')->assertRedirect('/login');
    }

    public function test_the_queue_lists_pending_comments_with_where_they_live(): void
    {
        $post = $this->newPost(attributes: ['title' => 'Usage-based billing']);
        $ada = $this->user();
        $this->comment($post, $ada, 'Please approve me');

        $this->actingAs($this->user('Mod'))->get('/comments/moderation')
            ->assertOk()
            ->assertSee('Please approve me')
            ->assertSee('Usage-based billing')
            ->assertSee('href="http://localhost/posts/'.$post->id.'#comment-', false)
            ->assertSee('Approve')
            ->assertSee('Reject')
            ->assertSeeInOrder(['Waiting', '1', 'Approved', '0', 'Deleted', '0']);
    }

    public function test_approving_publishes_the_comment(): void
    {
        Event::fake([CommentApproved::class]);
        $post = $this->newPost();
        $comment = $this->comment($post, $this->user(), 'Approve me');

        $this->actingAs($this->user('Bob'))->post("/comments/{$comment->id}/approve")->assertForbidden();

        $this->actingAs($this->user('Mod'))->from('/comments/moderation')->post("/comments/{$comment->id}/approve")
            ->assertRedirect('/comments/moderation')
            ->assertSessionHas('comments.status', 'Comment approved.');

        $this->assertTrue($comment->fresh()->isApproved());
        Event::assertDispatched(CommentApproved::class);

        $this->app['auth']->forgetGuards();
        $this->get("/posts/{$post->id}")->assertSee('Approve me');
    }

    public function test_approving_over_json(): void
    {
        $comment = $this->comment($this->newPost(), $this->user(), 'Approve me');

        $this->actingAs($this->user('Mod'))->postJson("/comments/{$comment->id}/approve")
            ->assertOk()->assertJsonPath('data.approved', true);
    }

    public function test_rejecting_deletes_and_the_deleted_tab_lists_it(): void
    {
        $post = $this->newPost();
        $comment = $this->comment($post, $this->user(), 'Spammy spam');
        $mod = $this->user('Mod');

        $this->actingAs($mod)->from('/comments/moderation')->delete("/comments/{$comment->id}")
            ->assertRedirect("/comments/moderation#comment-{$comment->id}");

        $this->assertSoftDeleted($comment);
        $this->actingAs($mod)->get('/comments/moderation')->assertDontSee('Spammy spam')->assertSee('You’re all caught up', false);
        $this->actingAs($mod)->get('/comments/moderation/deleted')->assertSee('Spammy spam')->assertDontSee('>Approve<', false);
    }

    public function test_the_approved_tab_and_json(): void
    {
        $post = $this->newPost();
        $comment = $this->comment($post, $this->user(), 'Good one');
        Comments::approve($comment);
        $this->comment($post, $this->user('Bob'), 'Pending one');

        $this->actingAs($this->user('Mod'))->get('/comments/moderation/approved')->assertSee('Good one')->assertDontSee('Pending one');
        $this->actingAs($this->user('Mod2', ['name' => 'Mod']))->getJson('/comments/moderation')
            ->assertOk()
            ->assertJsonPath('data.0.body', 'Pending one')
            ->assertJsonPath('meta.counts', ['pending' => 1, 'approved' => 1, 'deleted' => 0]);
        $this->get('/comments/moderation/unknown')->assertNotFound();
    }

    public function test_pending_comments_cannot_be_replied_to_or_reacted_to(): void
    {
        $post = $this->newPost();
        $comment = $this->comment($post, $this->user(), 'Waiting');
        $bob = $this->user('Bob');

        $this->actingAs($bob)->postJson("/comments/threads/post/{$post->id}", ['body' => 'Hi', 'parent_id' => $comment->id])->assertUnprocessable();
        $this->actingAs($bob)->postJson("/comments/{$comment->id}/reactions", ['emoji' => '👍'])->assertForbidden();
        $this->assertSame(1, Comment::count());
    }
}
