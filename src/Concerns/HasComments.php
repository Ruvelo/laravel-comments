<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Ruvelo\Comments\Models\Comment;

/**
 * Makes a model commentable. Override any of the comment*() methods to
 * close threads, link to the model, or notify its owners:
 *
 *     public function commentUrl(): ?string { return route('posts.show', $this); }
 *     public function commentNotifiables(): iterable { return [$this->author]; }
 *
 * Remember to list the model in comments.commentables (or your morph map).
 *
 * @mixin Model
 */
trait HasComments
{
    /**
     * Every comment, including pending ones. Use Comments::for($model) for
     * the ones readers see.
     *
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Adds `comments_count`: approved comments that aren't deleted.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWithCommentsCount(Builder $query): void
    {
        $query->withCount(['comments as comments_count' => fn (Builder $comments) => $comments->whereNotNull('approved_at')]);
    }

    /**
     * Whether people may still post. Existing comments stay visible.
     */
    public function commentsAreOpen(): bool
    {
        return true;
    }

    /**
     * Where the model lives, for permalinks, notifications and the
     * moderation page. Null when it has no page of its own.
     */
    public function commentUrl(): ?string
    {
        return null;
    }

    /**
     * How the model is named on the moderation page and in notifications.
     */
    public function commentTitle(): string
    {
        foreach (['title', 'name', 'subject'] as $attribute) {
            $value = $this->getAttribute($attribute);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return Str::headline(class_basename($this)).' #'.$this->getKey();
    }

    /**
     * Who hears about new comments when notifications are on, besides the
     * author of the comment being replied to. Usually the model's owner.
     *
     * @return iterable<int, mixed>
     */
    public function commentNotifiables(): iterable
    {
        return [];
    }
}
