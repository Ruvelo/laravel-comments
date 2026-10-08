<?php

declare(strict_types=1);

namespace Ruvelo\Comments\View\Components;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\View\Component;
use Ruvelo\Comments\Comments;
use Ruvelo\Comments\Models\Comment;
use Ruvelo\Comments\View\ThreadContext;

/**
 * The whole comment section for a model:
 *
 *     <x-comments::thread :for="$post" />
 *
 * Works as plain HTML forms; its small inline script then posts, edits,
 * reacts and previews without reloading the page. Query parameters
 * (comments_sort, comments_page, comments_reply, comments_edit) carry state
 * when JavaScript is off.
 */
class Thread extends Component
{
    public ThreadContext $ctx;

    /** @var LengthAwarePaginator<int, Comment> */
    public LengthAwarePaginator $comments;

    public int $count;

    public ?int $replyingTo;

    public ?int $editing;

    public function __construct(Model $for, ?string $sort = null, ?int $perPage = null)
    {
        /** @var Request $request */
        $request = request();
        $viewer = $request->user();

        $this->ctx = ThreadContext::for($for, $viewer, $sort ?? $request->string('comments_sort')->toString() ?: null);

        $this->comments = Comments::thread($for, $this->ctx->viewerModel(), $this->ctx->sort, $perPage, null, 'comments_page')
            ->withQueryString()
            ->fragment('comments');

        $this->count = Comments::countFor($for);
        $this->replyingTo = $request->integer('comments_reply') ?: self::formId($request, 'reply');
        $this->editing = $request->integer('comments_edit') ?: self::formId($request, 'edit');
    }

    public function render(): string
    {
        return 'comments::components.thread';
    }

    /**
     * After a failed or previewed submit without JavaScript, reopen the
     * reply or edit form it came from.
     */
    private static function formId(Request $request, string $kind): ?int
    {
        $form = $request->old('_comments_form');

        return is_string($form) && preg_match('/^'.$kind.'-(\d+)$/', $form, $match) ? (int) $match[1] : null;
    }
}
