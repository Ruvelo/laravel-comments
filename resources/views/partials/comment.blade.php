{{-- A comment with its replies, recursively. --}}
<li class="comments-item" id="{{ $comment->anchor() }}" data-comment="{{ $comment->id }}">
    @include('comments::partials.card')

    @if (($replyingTo ?? null) === $comment->id && $ctx->canReply($comment))
        @include('comments::partials.form', ['mode' => 'reply', 'comment' => $comment])
    @endif

    @if ($comment->relationLoaded('replies') && $comment->replies->isNotEmpty())
        <ol class="comments-replies">
            @foreach ($comment->replies as $reply)
                @include('comments::partials.comment', ['comment' => $reply])
            @endforeach
        </ol>
    @endif
</li>
