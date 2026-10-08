<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Ruvelo\Comments\Comments;
use Ruvelo\Comments\Tests\TestCase;

class ThreadViewTest extends TestCase
{
    public function test_guests_see_the_thread_and_an_invitation_to_sign_in_even_without_a_login_route(): void
    {
        $post = $this->newPost();
        $this->comment($post, $this->user(), 'Looks **great**');

        $this->get("/posts/{$post->id}")
            ->assertOk()
            ->assertSee('1 comment')
            ->assertSee('Looks <strong>great</strong>', false)
            ->assertSee('Sign in to comment.')
            ->assertDontSee('name="body"', false)
            ->assertDontSee('>Reply<', false);
    }

    public function test_guests_get_a_link_to_the_login_page_when_there_is_one(): void
    {
        Route::get('/login', fn () => 'Sign in')->name('login');
        app('router')->getRoutes()->refreshNameLookups();

        $this->get('/posts/'.$this->newPost()->id)
            ->assertOk()
            ->assertSee('<a href="http://localhost/login">Sign in to comment</a>', false)
            ->assertSee('Nobody has commented yet.');
    }

    public function test_signed_in_people_get_the_compose_form(): void
    {
        $post = $this->newPost();

        $this->actingAs($this->user())
            ->get("/posts/{$post->id}")
            ->assertOk()
            ->assertSee('No comments yet')
            ->assertSee('Start the conversation')
            ->assertSee('action="http://localhost/comments/threads/post/'.$post->id.'"', false)
            ->assertSee('name="website"', false)
            ->assertSee('data-comments-tab="preview"', false)
            ->assertSee('Post comment')
            ->assertSee('Markdown works');
    }

    public function test_actions_follow_the_gates(): void
    {
        $post = $this->newPost();
        $ada = $this->user();
        $bob = $this->user('Bob Reyes');
        $mine = $this->comment($post, $ada, 'Mine');
        $theirs = $this->comment($post, $bob, 'Theirs');

        $html = $this->actingAs($ada)->get("/posts/{$post->id}")->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, '>Reply</a>'));
        $this->assertStringContainsString('data-comments-edit="http://localhost/comments/'.$mine->id.'"', $html);
        $this->assertStringNotContainsString('data-comments-edit="http://localhost/comments/'.$theirs->id.'"', $html);
        $this->assertSame(1, substr_count($html, '>Delete</button>'));
    }

    public function test_moderators_can_delete_anything_but_edit_only_their_own(): void
    {
        $this->moderators('Mod');
        $post = $this->newPost();
        $this->comment($post, $this->user(), 'Theirs');

        $html = $this->actingAs($this->user('Mod'))->get("/posts/{$post->id}")->getContent();

        $this->assertSame(1, substr_count($html, '>Delete</button>'));
        $this->assertStringNotContainsString('>Edit</a>', $html);
    }

    public function test_a_deleted_comment_with_replies_stays_as_a_placeholder(): void
    {
        $post = $this->newPost();
        $ada = $this->user();
        $parent = $this->comment($post, $ada, 'Secret plan');
        $this->comment($post, $this->user('Bob Reyes'), 'A reply', $parent);
        $lonely = $this->comment($post, $ada, 'Lonely and deleted');
        Comments::delete($parent);
        Comments::delete($lonely);

        $this->get("/posts/{$post->id}")
            ->assertOk()
            ->assertSee('This comment was deleted.')
            ->assertSee('A reply')
            ->assertDontSee('Secret plan')
            ->assertDontSee('Lonely and deleted')
            ->assertSee('1 comment');
    }

    public function test_a_deleted_comment_whose_replies_are_deleted_disappears(): void
    {
        $post = $this->newPost();
        $ada = $this->user();
        $parent = $this->comment($post, $ada, 'Parent');
        Comments::delete($this->comment($post, $ada, 'Reply', $parent));
        Comments::delete($parent);

        $this->get("/posts/{$post->id}")->assertDontSee('This comment was deleted.')->assertSee('No comments yet');
    }

    public function test_pending_comments_show_only_to_their_author_and_moderators(): void
    {
        config(['comments.require_approval' => true]);
        $this->moderators('Mod');
        $post = $this->newPost();
        $ada = $this->user();
        $this->comment($post, $ada, 'Waiting for a mod');

        $this->get("/posts/{$post->id}")->assertDontSee('Waiting for a mod');
        $this->actingAs($this->user('Bob'))->get("/posts/{$post->id}")->assertDontSee('Waiting for a mod');
        $this->actingAs($ada)->get("/posts/{$post->id}")->assertSee('Waiting for a mod')->assertSee('Waiting for approval');
        $this->actingAs($this->user('Mod'))->get("/posts/{$post->id}")->assertSee('Waiting for a mod');
    }

    public function test_edited_comments_say_so_and_reactions_show_who_reacted(): void
    {
        $post = $this->newPost();
        $comment = $this->comment($post, $this->user(), 'First');
        Comments::update($comment, 'Second');
        Comments::react($comment, $this->user('Bob Reyes'), '🎉');
        Comments::react($comment, $this->user('Cy Mori'), '🎉');

        $this->get("/posts/{$post->id}")
            ->assertSee('· edited')
            ->assertSee('title="Bob Reyes, Cy Mori reacted with 🎉"', false)
            ->assertDontSee('React with 🎉'); // guests can't react
    }

    public function test_signed_in_people_get_reaction_buttons(): void
    {
        $post = $this->newPost();
        $comment = $this->comment($post, $this->user(), 'First');
        $bob = $this->user('Bob Reyes');
        Comments::react($comment, $bob, '👍');

        $this->actingAs($bob)->get("/posts/{$post->id}")
            ->assertSee('action="http://localhost/comments/'.$comment->id.'/reactions"', false)
            ->assertSee('aria-pressed="true" title="Bob Reyes reacted with 👍"', false)
            ->assertSee('aria-label="React with 😮"', false);
    }

    public function test_threads_sort_oldest_or_newest(): void
    {
        $post = $this->newPost();
        $ada = $this->user();
        $this->comment($post, $ada, 'First comment');
        $this->comment($post, $ada, 'Second comment');

        $this->assertMatchesRegularExpression('/First comment.*Second comment/s', $this->get("/posts/{$post->id}")->getContent());
        $this->assertMatchesRegularExpression('/Second comment.*First comment/s', $this->get("/posts/{$post->id}?comments_sort=newest")->getContent());
    }

    public function test_threads_paginate_top_level_comments(): void
    {
        config(['comments.per_page' => 2]);
        $post = $this->newPost();
        $ada = $this->user();
        foreach (['One', 'Two', 'Three'] as $body) {
            Comments::post($post, $ada, "Comment {$body}");
        }

        $this->get("/posts/{$post->id}")->assertSee('Comment Two')->assertDontSee('Comment Three')->assertSee('Page 1 of 2');
        $this->get("/posts/{$post->id}?comments_page=2")->assertSee('Comment Three')->assertDontSee('Comment One');
    }

    public function test_closed_threads_say_so(): void
    {
        $post = $this->newPost(attributes: ['closed' => true]);

        $this->actingAs($this->user())->get("/posts/{$post->id}")->assertSee('Comments are closed.')->assertDontSee('Post comment');
    }

    public function test_reply_and_edit_forms_open_without_javascript(): void
    {
        $post = $this->newPost();
        $ada = $this->user();
        $comment = $this->comment($post, $ada, 'Original text');

        $this->actingAs($ada)->get("/posts/{$post->id}?comments_reply={$comment->id}")
            ->assertSee('id="reply-'.$comment->id.'"', false)
            ->assertSee('name="parent_id" value="'.$comment->id.'"', false);

        $this->actingAs($ada)->get("/posts/{$post->id}?comments_edit={$comment->id}")
            ->assertSee('id="edit-'.$comment->id.'"', false)
            ->assertSee('Original text</textarea>', false)
            ->assertSee('Save changes');
    }

    public function test_a_custom_gate_can_stop_people_commenting(): void
    {
        Gate::define('comments-create', fn () => false);
        $post = $this->newPost();

        $this->actingAs($this->user())->get("/posts/{$post->id}")->assertDontSee('Post comment');
    }

    public function test_rendering_a_thread_takes_the_same_number_of_queries_however_big_it_is(): void
    {
        $post = $this->newPost();
        $viewer = $this->user('Viewer');

        $seed = function (int $threads) use ($post): void {
            for ($i = 0; $i < $threads; $i++) {
                $author = $this->user("Person{$i} ".uniqid(), ['email' => uniqid().'@example.com']);
                $top = Comments::post($post, $author, "Top {$i}");
                $reply = Comments::post($post, $this->user("Replier{$i} ".uniqid(), ['email' => uniqid().'@example.com']), "Reply {$i}", $top);
                Comments::react($top, $author, '👍');
                Comments::react($reply, $author, '🎉');
            }
        };

        $seed(2);
        $small = $this->countQueries(fn () => $this->actingAs($viewer)->get("/posts/{$post->id}")->assertOk());

        $seed(8);
        $large = $this->countQueries(fn () => $this->actingAs($viewer)->get("/posts/{$post->id}")->assertOk()->assertSee('Reply 7'));

        $this->assertSame($small, $large);
        $this->assertLessThanOrEqual(9, $large);
    }

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
