{{-- One comment, without its replies. Also returned to the script after an edit, delete or reaction. --}}
@php
    $isEditing = ($editing ?? null) === $comment->id && $ctx->canUpdate($comment);
@endphp
@if ($comment->trashed())
    <article class="comments-comment is-deleted" data-comments-card>
        <span class="comments-avatar comments-avatar--gone" aria-hidden="true">–</span>
        <div>
            <p class="comments-gone">This comment was deleted.</p>
        </div>
    </article>
@else
    <article class="comments-comment {{ $comment->isPending() ? 'is-pending' : '' }}" data-comments-card
             @if ($ctx->canUpdate($comment)) data-comments-source="{{ $comment->body }}" @endif>
        @include('comments::partials.avatar', ['url' => $comment->authorAvatarUrl(), 'initials' => $comment->authorInitials()])
        <div>
            <header class="comments-meta">
                <span class="comments-author">{{ $comment->authorName() }}</span>
                <a href="#{{ $comment->anchor() }}"><time datetime="{{ $comment->created_at->toIso8601String() }}" title="{{ $comment->created_at->toDayDateTimeString() }}">{{ $comment->created_at->diffForHumans() }}</time></a>
                @if ($comment->isEdited())
                    <span title="Edited {{ $comment->edited_at?->diffForHumans() }}">· edited</span>
                @endif
                @if ($comment->isPending())
                    <span class="comments-chip comments-chip--pending">Waiting for approval</span>
                @endif
            </header>

            @if ($isEditing)
                @include('comments::partials.form', ['mode' => 'edit', 'comment' => $comment])
            @else
                <div class="comments-body comments-prose">{!! $comment->html !!}</div>

                <footer class="comments-actions">
                    @include('comments::partials.reactions')

                    @if ($ctx->canReply($comment))
                        <a class="comments-action" href="{{ request()->fullUrlWithQuery(['comments_reply' => $comment->id, 'comments_edit' => null]) }}#reply-{{ $comment->id }}" data-comments-reply="{{ $comment->id }}">Reply</a>
                    @endif
                    @if ($ctx->canUpdate($comment))
                        <a class="comments-action" href="{{ request()->fullUrlWithQuery(['comments_edit' => $comment->id, 'comments_reply' => null]) }}#{{ $comment->anchor() }}" data-comments-edit="{{ route('comments.update', $comment) }}">Edit</a>
                    @endif
                    @if ($ctx->canDelete($comment))
                        <form method="post" action="{{ route('comments.destroy', $comment) }}" data-comments-form="delete">
                            @csrf
                            <input type="hidden" name="_method" value="DELETE">
                            <button type="submit" class="comments-action comments-action--danger">Delete</button>
                        </form>
                    @endif
                </footer>
            @endif
        </div>
    </article>
@endif
