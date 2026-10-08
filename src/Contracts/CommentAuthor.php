<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Contracts;

/**
 * Lets a model choose how it appears beside its comments. Optional: models
 * without it show their `name` attribute (comments.author_name_attribute)
 * and an initials avatar. The IsCommentAuthor trait implements it.
 */
interface CommentAuthor
{
    public function commentAuthorName(): string;

    /**
     * An image URL, or null for an initials avatar.
     */
    public function commentAuthorAvatarUrl(): ?string;
}
