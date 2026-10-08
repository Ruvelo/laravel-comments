<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One person's emoji on one comment. Toggle through Comments::react().
 *
 * @property int $id
 * @property int $comment_id
 * @property string $reactor_type
 * @property int|string $reactor_id
 * @property string $emoji
 * @property Carbon|null $created_at
 * @property-read Model|null $reactor
 * @property-read Comment $comment
 */
class Reaction extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('comments.table_prefix', 'ruvelo_').'comment_reactions';
    }

    /**
     * @return BelongsTo<Comment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class)->withTrashed();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reactor(): MorphTo
    {
        return $this->morphTo();
    }

    public function isBy(Model $person): bool
    {
        return $this->reactor_type === $person->getMorphClass()
            && (string) $this->reactor_id === (string) $person->getKey();
    }
}
