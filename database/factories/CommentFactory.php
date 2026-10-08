<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;
use Ruvelo\Comments\Comments;
use Ruvelo\Comments\Models\Comment;

/**
 * For your own tests:
 *
 *     Comment::factory()->on($post)->by($user)->create();
 *     Comment::factory()->on($post)->replyTo($comment)->create();
 *     Comment::factory()->on($post)->pending()->create();
 *
 * The body is rendered like a real comment's. Without by(), the author is a
 * new user from your user model's factory.
 *
 * @extends Factory<Comment>
 */
final class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'author_type' => fn (): string => (new (Comments::userModel()))->getMorphClass(),
            'author_id' => function (): mixed {
                $class = Comments::userModel();

                if (! method_exists($class, 'factory')) {
                    throw new LogicException('Give the comment an author with ->by($user): '.$class.' has no factory.');
                }

                return $class::factory()->create()->getKey();
            },
            'body' => $this->faker->sentences(2, true),
            'html' => fn (array $attributes): string => Comments::render((string) $attributes['body']),
            'approved_at' => Carbon::now(),
        ];
    }

    public function on(Model $commentable): self
    {
        return $this->state(fn () => [
            'commentable_type' => $commentable->getMorphClass(),
            'commentable_id' => $commentable->getKey(),
        ]);
    }

    public function by(Model $author): self
    {
        return $this->state(fn () => [
            'author_type' => $author->getMorphClass(),
            'author_id' => $author->getKey(),
        ]);
    }

    public function replyTo(Comment $parent): self
    {
        return $this->state(fn () => [
            'commentable_type' => $parent->commentable_type,
            'commentable_id' => $parent->commentable_id,
            'parent_id' => $parent->id,
            'root_id' => $parent->root_id ?? $parent->id,
            'depth' => $parent->depth + 1,
        ]);
    }

    public function body(string $markdown): self
    {
        return $this->state(fn () => ['body' => $markdown]);
    }

    public function pending(): self
    {
        return $this->state(fn () => ['approved_at' => null]);
    }

    public function edited(): self
    {
        return $this->state(fn () => ['edited_at' => Carbon::now()]);
    }
}
