<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Ruvelo\Comments\Concerns\HasComments;
use Ruvelo\Comments\Exceptions\NotCommentable;

/**
 * The allow-list of commentable types. Requests name a model by a short
 * alias from comments.commentables or the morph map; class names sent by a
 * client are never used to build a query.
 */
final class Commentables
{
    /**
     * The model a request points at.
     *
     * @throws NotCommentable
     * @throws ModelNotFoundException<Model>
     */
    public static function find(string $type, string $id): Model
    {
        $class = self::classFor($type) ?? throw new NotCommentable($type);

        /** @var Model $model */
        $model = new $class;

        return $model->newQuery()->where($model->getKeyName(), $id)->firstOrFail();
    }

    /**
     * @return class-string<Model>|null
     */
    public static function classFor(string $type): ?string
    {
        $configured = config('comments.commentables', []);
        $class = is_array($configured) && isset($configured[$type]) ? $configured[$type] : Relation::getMorphedModel($type);

        return is_string($class) && is_subclass_of($class, Model::class) && self::isCommentable($class) ? $class : null;
    }

    /**
     * The alias a model goes by in URLs.
     *
     * @throws NotCommentable
     */
    public static function alias(Model $model): string
    {
        foreach (config('comments.commentables', []) as $alias => $class) {
            if ($model instanceof $class) {
                return (string) $alias;
            }
        }

        $morph = $model->getMorphClass();
        if (Relation::getMorphedModel($morph) !== null && self::isCommentable($model::class)) {
            return $morph;
        }

        throw new NotCommentable($model::class);
    }

    public static function isCommentable(string $class): bool
    {
        return class_exists($class)
            && is_subclass_of($class, Model::class)
            && in_array(HasComments::class, class_uses_recursive($class), true);
    }

    /**
     * Calls one of HasComments' optional hooks, if the model has it.
     */
    public static function call(?Model $commentable, string $method, mixed $default = null): mixed
    {
        return $commentable !== null && method_exists($commentable, $method) ? $commentable->{$method}() : $default;
    }
}
