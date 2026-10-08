<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Ruvelo\Comments\Models\Comment;

/**
 * Loads one page of a thread, the way a given viewer sees it, in a fixed
 * number of queries however many comments and replies there are.
 *
 * Everyone sees approved comments; authors also see their own pending
 * ones; moderators see everything. A deleted comment stays as a
 * placeholder while it has replies, and disappears otherwise.
 */
final class Thread
{
    /**
     * @return LengthAwarePaginator<int, Comment>
     */
    public static function load(
        Model $commentable,
        ?Model $viewer = null,
        bool $moderator = false,
        string $sort = 'oldest',
        int $perPage = 20,
        ?int $page = null,
        string $pageName = 'page',
    ): LengthAwarePaginator {
        $table = (new Comment)->getTable();

        $roots = self::visible(Comment::withTrashed()->on($commentable), $viewer, $moderator, $table)
            ->whereNull('parent_id')
            ->where(fn (Builder $query) => $query
                ->whereNull($table.'.deleted_at')
                ->orWhereExists(fn (QueryBuilder $replies) => self::visibleReplies($replies, $viewer, $moderator, $table)))
            ->orderBy('id', $sort === 'newest' ? 'desc' : 'asc')
            ->paginate($perPage, ['*'], $pageName, $page);

        $rootIds = $roots->getCollection()->modelKeys();

        $descendants = $rootIds === []
            ? new Collection
            : self::visible(Comment::withTrashed()->on($commentable), $viewer, $moderator, $table)
                ->whereIn('root_id', $rootIds)
                ->orderBy('id')
                ->get();

        $all = $roots->getCollection()->toBase()->merge($descendants)->all();
        (new Collection($all))->load(['author', 'reactions.reactor']);

        $byParent = $descendants->groupBy('parent_id');
        $roots->setCollection($roots->getCollection()->filter(fn (Comment $root): bool => self::attach($root, $byParent))->values());

        return $roots;
    }

    /**
     * @param  Builder<Comment>  $query
     * @return Builder<Comment>
     */
    private static function visible(Builder $query, ?Model $viewer, bool $moderator, string $table): Builder
    {
        if ($moderator) {
            return $query;
        }

        if ($viewer === null) {
            return $query->whereNotNull($table.'.approved_at');
        }

        return $query->where(fn (Builder $visible) => $visible
            ->whereNotNull($table.'.approved_at')
            ->orWhere(fn (Builder $mine) => $mine->by($viewer)));
    }

    private static function visibleReplies(QueryBuilder $query, ?Model $viewer, bool $moderator, string $table): void
    {
        $query->from($table, 'r')
            ->selectRaw('1')
            ->whereColumn('r.root_id', $table.'.id')
            ->whereNull('r.deleted_at');

        if (! $moderator) {
            $query->where(fn (QueryBuilder $visible) => $visible
                ->whereNotNull('r.approved_at')
                ->when($viewer !== null, fn (QueryBuilder $own) => $own->orWhere(fn (QueryBuilder $mine) => $mine
                    ->where('r.author_type', $viewer?->getMorphClass())
                    ->where('r.author_id', $viewer?->getKey()))));
        }
    }

    /**
     * Gives the comment its visible replies and reports whether it should
     * be shown at all: a deleted comment only stays for its replies.
     *
     * @param  \Illuminate\Support\Collection<array-key, Collection<int, Comment>>  $byParent
     */
    private static function attach(Comment $comment, \Illuminate\Support\Collection $byParent): bool
    {
        $replies = ($byParent->get($comment->id) ?? new Collection)
            ->filter(fn (Comment $reply): bool => self::attach($reply, $byParent))
            ->values();

        $comment->setRelation('replies', $replies);

        return ! $comment->trashed() || $replies->isNotEmpty();
    }
}
