<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Tests\Feature;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Ruvelo\Comments\Comments;
use Ruvelo\Comments\Models\Comment;
use Ruvelo\Comments\Models\Reaction;
use Ruvelo\Comments\Tests\Fixtures\Post;
use Ruvelo\Comments\Tests\Fixtures\User;
use Ruvelo\Comments\Tests\Fixtures\Video;
use Ruvelo\Comments\Tests\TestCase;

class HttpTest extends TestCase
{
    public function test_a_form_post_saves_the_comment_and_goes_back_to_it(): void
    {
        $post = $this->newPost();
        $ada = $this->user();

        $response = $this->actingAs($ada)
            ->from("/posts/{$post->id}")
            ->post("/comments/threads/post/{$post->id}", ['body' => 'Hello *there*', 'website' => '']);

        $comment = Comment::query()->sole();
        $response->assertRedirect("http://localhost/posts/{$post->id}#comment-{$comment->id}")
            ->assertSessionHas('comments.status', 'Comment posted.');
        $this->assertTrue($comment->isAuthoredBy($ada));
        $this->assertTrue($comment->commentable->is($post));
    }

    public function test_a_json_post_returns_the_comment_and_its_html(): void
    {
        $post = $this->newPost();
        $ada = $this->user();
        $parent = $this->comment($post, $this->user('Bob Reyes'), 'Parent');

        $response = $this->actingAs($ada)->postJson("/comments/threads/post/{$post->id}", ['body' => 'Hi', 'parent_id' => $parent->id]);

        $id = Comment::query()->latest('id')->value('id');
        $response->assertCreated()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.parent_id', $parent->id)
            ->assertJsonPath('data.depth', 1)
            ->assertJsonPath('data.author.name', 'Ada Lovelace')
            ->assertJsonPath('data.author.initials', 'AL')
            ->assertJsonPath('data.html', "<p>Hi</p>\n")
            ->assertJsonPath('data.can', ['reply' => true, 'update' => true, 'delete' => true, 'react' => true])
            ->assertJsonPath('data.url', "http://localhost/comments/{$id}")
            ->assertJsonPath('comments_count', 2)
            ->assertJsonPath('message', 'Comment posted.');
        $this->assertStringContainsString('<li class="comments-item" id="comment-'.$id.'"', $response->json('html'));
    }

    public function test_guests_cannot_post(): void
    {
        $post = $this->newPost();

        $this->postJson("/comments/threads/post/{$post->id}", ['body' => 'Hi'])->assertUnauthorized();
        $this->post("/comments/threads/post/{$post->id}", ['body' => 'Hi'])->assertForbidden();

        Route::get('/login', fn () => 'Sign in')->name('login');
        app('router')->getRoutes()->refreshNameLookups();
        $this->post("/comments/threads/post/{$post->id}", ['body' => 'Hi'])->assertRedirect('/login');

        $this->assertSame(0, Comment::count());
    }

    public function test_the_create_gate_can_refuse(): void
    {
        Gate::define('comments-create', fn (User $user, $commentable) => $user->name === 'Editor');
        $post = $this->newPost();

        $this->actingAs($this->user())->postJson("/comments/threads/post/{$post->id}", ['body' => 'Hi'])
            ->assertForbidden()->assertJsonPath('message', 'You can’t do that.');
        $this->actingAs($this->user('Editor'))->postJson("/comments/threads/post/{$post->id}", ['body' => 'Hi'])->assertCreated();
    }

    public function test_only_registered_commentable_types_are_accepted(): void
    {
        $ada = $this->actingAs($this->user());
        $post = $this->newPost();

        foreach ([
            'video', // has the trait, but isn't registered
            urlencode(Video::class),
            urlencode(Post::class), // class names never work, even registered ones
            urlencode(User::class),
            'nothing',
        ] as $type) {
            $ada->postJson("/comments/threads/{$type}/{$post->id}", ['body' => 'Hi'])->assertNotFound();
            $ada->getJson("/comments/threads/{$type}/{$post->id}")->assertNotFound();
        }

        $ada->postJson('/comments/threads/post/999', ['body' => 'Hi'])->assertNotFound();
        $this->assertSame(0, Comment::count());
    }

    public function test_registered_types_need_the_trait(): void
    {
        config(['comments.commentables' => ['user' => User::class]]);
        $user = $this->user();

        $this->actingAs($user)->postJson("/comments/threads/user/{$user->id}", ['body' => 'Hi'])->assertNotFound();
    }

    public function test_morph_map_aliases_work(): void
    {
        config(['comments.commentables' => []]);
        Relation::morphMap(['article' => Post::class]);
        $post = $this->newPost();

        try {
            $this->actingAs($this->user())->postJson("/comments/threads/article/{$post->id}", ['body' => 'Hi'])->assertCreated();
            $this->assertSame('article', Comment::query()->value('commentable_type'));
        } finally {
            Relation::morphMap([], false);
        }
    }

    public function test_validation_errors(): void
    {
        config(['comments.max_length' => 20]);
        $post = $this->newPost();
        $ada = $this->actingAs($this->user());

        $ada->postJson("/comments/threads/post/{$post->id}", ['body' => ''])
            ->assertUnprocessable()->assertJsonPath('errors.body.0', 'Write something before posting.');
        $ada->postJson("/comments/threads/post/{$post->id}", ['body' => str_repeat('a', 21)])
            ->assertUnprocessable()->assertJsonPath('errors.body.0', 'Keep comments under 20 characters.');

        $ada->from("/posts/{$post->id}")->post("/comments/threads/post/{$post->id}", ['body' => '', '_comments_form' => 'new'])
            ->assertRedirect("/posts/{$post->id}")
            ->assertSessionHasErrors(['body'], null, 'comments');
    }

    public function test_replying_to_a_deleted_comment_is_refused(): void
    {
        $post = $this->newPost();
        $ada = $this->user();
        $parent = $this->comment($post, $ada, 'Gone');
        Comments::delete($parent);

        $this->actingAs($ada)->postJson("/comments/threads/post/{$post->id}", ['body' => 'Hi', 'parent_id' => $parent->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'That comment was deleted or isn’t published yet, so it can’t be replied to.');
    }

    public function test_closed_threads_answer_403(): void
    {
        $post = $this->newPost(attributes: ['closed' => true]);

        $this->actingAs($this->user())->postJson("/comments/threads/post/{$post->id}", ['body' => 'Hi'])
            ->assertForbidden()->assertJsonPath('message', 'Comments are closed here.');
    }

    public function test_the_honeypot_quietly_drops_bots(): void
    {
        $post = $this->newPost();
        $bot = $this->actingAs($this->user());

        $bot->postJson("/comments/threads/post/{$post->id}", ['body' => 'Buy now', 'website' => 'https://spam.example'])->assertStatus(202);
        $bot->from("/posts/{$post->id}")->post("/comments/threads/post/{$post->id}", ['body' => 'Buy now', 'website' => 'x'])
            ->assertRedirect("/posts/{$post->id}");

        $this->assertSame(0, Comment::count());
    }

    public function test_posting_is_rate_limited_per_author(): void
    {
        config(['comments.rate_limit' => ['max' => 2, 'minutes' => 1]]);
        $post = $this->newPost();
        $ada = $this->user();

        $this->actingAs($ada)->postJson("/comments/threads/post/{$post->id}", ['body' => 'One'])->assertCreated();
        $this->actingAs($ada)->postJson("/comments/threads/post/{$post->id}", ['body' => 'Two'])->assertCreated();
        $this->actingAs($ada)->postJson("/comments/threads/post/{$post->id}", ['body' => 'Three'])
            ->assertStatus(429)
            ->assertJsonPath('message', fn (string $message) => str_starts_with($message, 'You’re posting quickly. Try again in'));

        $this->actingAs($this->user('Bob'))->postJson("/comments/threads/post/{$post->id}", ['body' => 'Mine'])->assertCreated();
        $this->assertSame(3, Comment::count());

        config(['comments.rate_limit.max' => null]);
        $this->actingAs($ada)->postJson("/comments/threads/post/{$post->id}", ['body' => 'Unlimited'])->assertCreated();
    }

    public function test_authors_edit_their_own_comments(): void
    {
        $post = $this->newPost();
        $ada = $this->user();
        $comment = $this->comment($post, $ada, 'Typo');

        $this->actingAs($this->user('Bob'))->patchJson("/comments/{$comment->id}", ['body' => 'Hacked'])->assertForbidden();

        $this->actingAs($ada)->patchJson("/comments/{$comment->id}", ['body' => 'Fixed'])
            ->assertOk()
            ->assertJsonPath('data.body', 'Fixed')
            ->assertJsonPath('data.edited_at', fn ($value) => is_string($value))
            ->assertJsonPath('message', 'Comment updated.');
        $this->assertSame('Fixed', $comment->fresh()->body);

        $this->actingAs($ada)->from("/posts/{$post->id}")->patch("/comments/{$comment->id}", ['body' => 'Again'])
            ->assertRedirect("http://localhost/posts/{$post->id}#comment-{$comment->id}");
    }

    public function test_the_edit_window_closes(): void
    {
        config(['comments.edit_window' => 15]);
        $post = $this->newPost();
        $ada = $this->user();
        $comment = $this->comment($post, $ada, 'Typo');

        Carbon::setTestNow(now()->addMinutes(16));

        $this->actingAs($ada)->patchJson("/comments/{$comment->id}", ['body' => 'Too late'])->assertForbidden();
        $this->actingAs($ada)->deleteJson("/comments/{$comment->id}")->assertOk();
    }

    public function test_authors_and_moderators_delete(): void
    {
        $this->moderators('Mod');
        $post = $this->newPost();
        $ada = $this->user();
        $first = $this->comment($post, $ada, 'First');
        $second = $this->comment($post, $ada, 'Second');

        $this->actingAs($this->user('Bob'))->deleteJson("/comments/{$first->id}")->assertForbidden();

        $this->actingAs($ada)->deleteJson("/comments/{$first->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true)
            ->assertJsonPath('data.body', null)
            ->assertJsonPath('data.author', null)
            ->assertJsonPath('comments_count', 1);
        $this->assertStringContainsString('This comment was deleted.', $this->deleteJson("/comments/{$second->id}")->json('html') ?? '');

        $this->assertSoftDeleted($second);
        $this->actingAs($this->user('Mod'))->deleteJson("/comments/{$first->id}")->assertNotFound();
    }

    public function test_reactions_toggle_through_the_route(): void
    {
        $post = $this->newPost();
        $comment = $this->comment($post, $this->user(), 'Nice');
        $bob = $this->user('Bob Reyes');

        $this->actingAs($bob)->postJson("/comments/{$comment->id}/reactions", ['emoji' => '🎉'])
            ->assertOk()
            ->assertJsonPath('added', true)
            ->assertJsonPath('reactions', [['emoji' => '🎉', 'count' => 1, 'reacted' => true, 'names' => ['Bob Reyes']]])
            ->assertJsonPath('html', fn (string $html) => str_contains($html, 'aria-pressed="true"'));

        $this->actingAs($bob)->postJson("/comments/{$comment->id}/reactions", ['emoji' => '🎉'])
            ->assertOk()->assertJsonPath('added', false)->assertJsonPath('reactions', []);

        $this->actingAs($bob)->postJson("/comments/{$comment->id}/reactions", ['emoji' => '💩'])
            ->assertUnprocessable()->assertJsonPath('message', 'Pick one of the offered reactions.');

        $this->actingAs($bob)->from("/posts/{$post->id}")->post("/comments/{$comment->id}/reactions", ['emoji' => '👍'])
            ->assertRedirect("http://localhost/posts/{$post->id}#comment-{$comment->id}");
        $this->assertSame(1, Reaction::count());

        $this->app['auth']->forgetGuards();
        $this->postJson("/comments/{$comment->id}/reactions", ['emoji' => '👍'])->assertUnauthorized();
    }

    public function test_deleted_comments_take_no_reactions(): void
    {
        $post = $this->newPost();
        $comment = $this->comment($post, $this->user(), 'Nice');
        Comments::delete($comment);

        $this->actingAs($this->user('Bob'))->postJson("/comments/{$comment->id}/reactions", ['emoji' => '🎉'])->assertNotFound();
    }

    public function test_the_preview_renders_markdown_safely_for_signed_in_people(): void
    {
        $this->postJson('/comments/preview', ['body' => '**hi**'])->assertUnauthorized();

        $this->actingAs($this->user())->postJson('/comments/preview', ['body' => '**hi** <script>x</script>'])
            ->assertOk()
            ->assertExactJson(['html' => "<p><strong>hi</strong> &lt;script&gt;x&lt;/script&gt;</p>\n"]);

        $this->actingAs($this->user('Bob'))->post('/comments/preview', ['body' => '*hi*'])
            ->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8')->assertSee('<em>hi</em>', false);
    }

    public function test_the_preview_works_without_javascript(): void
    {
        $post = $this->newPost();
        $ada = $this->user();

        $this->actingAs($ada)->from("/posts/{$post->id}")
            ->post("/comments/threads/post/{$post->id}", ['body' => 'A **draft**', 'action' => 'preview', '_comments_form' => 'new'])
            ->assertRedirect("http://localhost/posts/{$post->id}#new")
            ->assertSessionHas('comments.preview');

        $this->assertSame(0, Comment::count());

        $this->actingAs($ada)->get("/posts/{$post->id}")
            ->assertSee('A **draft**</textarea>', false)
            ->assertSee('A <strong>draft</strong>', false);
    }

    public function test_permalinks_redirect_to_the_comment_on_its_page(): void
    {
        $post = $this->newPost();
        $comment = $this->comment($post, $this->user(), 'Here');

        $this->get("/comments/{$comment->id}")->assertRedirect("http://localhost/posts/{$post->id}#comment-{$comment->id}");
        $this->get('/comments/999')->assertNotFound();
    }

    public function test_the_thread_json_lists_top_level_comments_with_nested_replies(): void
    {
        config(['comments.per_page' => 2]);
        $post = $this->newPost();
        $ada = $this->user();
        $bob = $this->user('Bob Reyes');
        $first = $this->comment($post, $ada, 'First');
        $reply = $this->comment($post, $bob, 'Reply', $first);
        Comments::react($reply, $ada, '❤️');
        $this->comment($post, $bob, 'Second');
        $this->comment($post, $bob, 'Third');

        $response = $this->actingAs($ada)->getJson("/comments/threads/post/{$post->id}")->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.body', 'First')
            ->assertJsonPath('data.0.replies.0.body', 'Reply')
            ->assertJsonPath('data.0.replies.0.reactions.0', ['emoji' => '❤️', 'count' => 1, 'reacted' => true, 'names' => ['Ada Lovelace']])
            ->assertJsonPath('data.0.replies.0.can', ['reply' => true, 'update' => false, 'delete' => false, 'react' => true])
            ->assertJsonPath('data.0.replies.0.replies', [])
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.comments_count', 4)
            ->assertJsonPath('meta.can_comment', true)
            ->assertJsonPath('meta.max_depth', 2)
            ->assertJsonPath('meta.reactions', ['👍', '❤️', '🎉', '😄', '😮', '😢']);

        $this->assertSame([
            'id', 'parent_id', 'depth', 'author', 'body', 'html', 'created_at', 'edited_at',
            'approved', 'deleted', 'url', 'reactions', 'can', 'replies',
        ], array_keys($response->json('data.0')));

        $this->app['auth']->forgetGuards();
        $this->getJson("/comments/threads/post/{$post->id}?sort=newest&page=2")
            ->assertJsonPath('data.0.body', 'First')
            ->assertJsonPath('data.0.can.reply', false)
            ->assertJsonPath('meta.can_comment', false);
    }
}
