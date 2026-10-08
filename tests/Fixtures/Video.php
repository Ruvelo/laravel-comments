<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Ruvelo\Comments\Concerns\HasComments;

/**
 * Commentable, but never registered in comments.commentables.
 */
class Video extends Model
{
    use HasComments;

    protected $table = 'posts';

    protected $guarded = [];
}
