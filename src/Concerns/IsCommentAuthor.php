<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Concerns;

/**
 * Default CommentAuthor implementation for your user model. Override either
 * method to change the name or give people real avatars:
 *
 *     class User extends Authenticatable implements CommentAuthor
 *     {
 *         use IsCommentAuthor;
 *
 *         public function commentAuthorAvatarUrl(): ?string
 *         {
 *             return $this->profile_photo_url;
 *         }
 *     }
 */
trait IsCommentAuthor
{
    public function commentAuthorName(): string
    {
        $name = $this->getAttribute(config('comments.author_name_attribute', 'name'));

        return is_scalar($name) && (string) $name !== '' ? (string) $name : 'Someone';
    }

    public function commentAuthorAvatarUrl(): ?string
    {
        return null;
    }
}
