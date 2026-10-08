<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Ruvelo\Comments\Contracts\CommentAuthor;
use Ruvelo\Comments\Database\Factories\CommentFactory;

/**
 * One comment. Write through Ruvelo\Comments\Comments, which keeps the
 * rendered HTML, threading, moderation and events in step.
 *
 * @property int $id
 * @property string $commentable_type
 * @property int|string $commentable_id
 * @property string $author_type
 * @property int|string $author_id
 * @property int|null $parent_id
 * @property int|null $root_id
 * @property int $depth
 * @property string $body
 * @property string $html
 * @property Carbon|null $edited_at
 * @property Carbon|null $approved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Model|null $author
 * @property-read Model|null $commentable
 * @property-read Collection<int, Comment> $replies
 * @property-read Collection<int, Reaction> $reactions
 */
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = ['id'];

    protected $attributes = ['depth' => 0, 'html' => ''];

    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'parent_id' => 'integer',
            'root_id' => 'integer',
            'edited_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    protected static function newFactory(): CommentFactory
    {
        return CommentFactory::new();
    }

    public function getTable(): string
    {
        return config('comments.table_prefix', '').'comments';
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Comment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->withTrashed();
    }

    /**
     * Direct replies. When a thread is loaded for display, this holds only
     * the replies the viewer may see, oldest first.
     *
     * @return HasMany<Comment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest('id');
    }

    /**
     * @return HasMany<Reaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class)->oldest('id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->whereNotNull('approved_at');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('approved_at');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOn(Builder $query, Model $commentable): void
    {
        $query->where('commentable_type', $commentable->getMorphClass())
            ->where('commentable_id', $commentable->getKey());
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeBy(Builder $query, Model $author): void
    {
        $query->where('author_type', $author->getMorphClass())
            ->where('author_id', $author->getKey());
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function isPending(): bool
    {
        return $this->approved_at === null;
    }

    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }

    public function isDeleted(): bool
    {
        return $this->trashed();
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    public function isAuthoredBy(?Model $person): bool
    {
        return $person !== null
            && $this->author_type === $person->getMorphClass()
            && (string) $this->author_id === (string) $person->getKey();
    }

    public function authorName(): string
    {
        return self::nameOf($this->author);
    }

    public function authorAvatarUrl(): ?string
    {
        return $this->author instanceof CommentAuthor ? $this->author->commentAuthorAvatarUrl() : null;
    }

    public function authorInitials(): string
    {
        return self::initialsOf($this->authorName());
    }

    /**
     * How a person is shown: their CommentAuthor name, else their name
     * attribute.
     */
    public static function nameOf(?Model $person): string
    {
        if ($person === null) {
            return 'Deleted user';
        }

        if ($person instanceof CommentAuthor) {
            return $person->commentAuthorName();
        }

        $name = $person->getAttribute(config('comments.author_name_attribute', 'name'));

        return is_scalar($name) && (string) $name !== '' ? (string) $name : 'Someone';
    }

    public static function initialsOf(string $name): string
    {
        $words = preg_split('/[\s._-]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];

        return mb_strtoupper(implode('', array_map(
            fn (string $word): string => mb_substr($word, 0, 1),
            array_slice($words, 0, 2),
        )));
    }

    /**
     * The body as plain text, shortened: for notifications and listings.
     */
    public function excerpt(int $length = 140): string
    {
        $text = html_entity_decode(strip_tags($this->html), ENT_QUOTES | ENT_HTML5);

        return Str::limit(trim((string) preg_replace('/\s+/u', ' ', $text)), $length);
    }

    /**
     * Reactions grouped by emoji, in the configured order.
     *
     * @return list<array{emoji: string, count: int, reacted: bool, names: list<string>}>
     */
    public function reactionSummary(?Model $viewer = null): array
    {
        $summary = [];

        foreach ($this->reactions->groupBy('emoji') as $emoji => $reactions) {
            $summary[(string) $emoji] = [
                'emoji' => (string) $emoji,
                'count' => $reactions->count(),
                'reacted' => $viewer !== null && $reactions->contains(fn (Reaction $reaction): bool => $reaction->isBy($viewer)),
                'names' => array_values($reactions->map(fn (Reaction $reaction): string => self::nameOf($reaction->reactor))->all()),
            ];
        }

        $order = array_flip(array_values(config('comments.reactions', [])));
        uksort($summary, fn (string $a, string $b): int => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));

        return array_values($summary);
    }

    /**
     * The anchor this comment has on its page.
     */
    public function anchor(): string
    {
        return 'comment-'.$this->id;
    }
}
