<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ruvelo\Comments\Concerns\HasComments;

/**
 * @property int $id
 * @property string $title
 * @property bool $closed
 * @property int|null $user_id
 * @property-read User|null $owner
 */
class Post extends Model
{
    use HasComments;

    protected $table = 'posts';

    protected $guarded = [];

    protected $casts = ['closed' => 'boolean'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function commentsAreOpen(): bool
    {
        return ! $this->closed;
    }

    public function commentUrl(): ?string
    {
        return url('/posts/'.$this->id);
    }

    /**
     * @return iterable<int, User|null>
     */
    public function commentNotifiables(): iterable
    {
        return [$this->owner];
    }
}
