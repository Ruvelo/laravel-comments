<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Ruvelo\Comments\Concerns\IsCommentAuthor;
use Ruvelo\Comments\Contracts\CommentAuthor;

class User extends Authenticatable implements CommentAuthor
{
    use IsCommentAuthor;
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    public function commentAuthorAvatarUrl(): ?string
    {
        $avatar = $this->getAttribute('avatar');

        return is_string($avatar) ? $avatar : null;
    }
}
