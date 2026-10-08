<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ruvelo\Comments\Comments;
use Ruvelo\Comments\Http\Resources\CommentResource;
use Ruvelo\Comments\Models\Comment;
use Symfony\Component\HttpFoundation\Response;

/**
 * The moderation queue, for people who pass the comments-moderate gate.
 * Rejecting is deleting, through the comment's DELETE route.
 */
class ModerationController extends Controller
{
    public const STATUSES = ['pending' => 'Waiting', 'approved' => 'Approved', 'deleted' => 'Deleted'];

    public function index(Request $request, string $status = 'pending'): Response
    {
        Comments::authorize('comments-moderate', $request->user());

        $query = fn () => Comment::query();
        $counts = [
            'pending' => $query()->pending()->count(),
            'approved' => $query()->approved()->count(),
            'deleted' => Comment::onlyTrashed()->count(),
        ];

        $comments = match ($status) {
            'approved' => $query()->approved()->latest('id'),
            'deleted' => Comment::onlyTrashed()->latest('deleted_at'),
            default => $query()->pending()->oldest('id'),
        };

        $comments = $comments->with(['author', 'commentable', 'parent.author'])
            ->paginate((int) config('comments.per_page', 20))
            ->withQueryString();

        if ($request->expectsJson()) {
            return CommentResource::collection($comments)
                ->additional(['meta' => ['counts' => $counts]])
                ->response();
        }

        return response()->view('comments::moderation', [
            'status' => $status,
            'statuses' => self::STATUSES,
            'counts' => $counts,
            'comments' => $comments,
        ]);
    }

    public function approve(Request $request, string $commentId): JsonResponse|RedirectResponse
    {
        Comments::authorize('comments-moderate', $request->user());

        $comment = Comments::approve(Comment::query()->findOrFail($commentId));

        if ($request->expectsJson()) {
            return (new CommentResource($comment->load(['author', 'commentable'])))->response();
        }

        return back()->with('comments.status', 'Comment approved.');
    }
}
