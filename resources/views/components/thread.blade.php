{{-- <x-comments::thread :for="$post" />: the whole comment section for a model. --}}
@once
    @include('comments::partials.styles')
@endonce

<section {{ $attributes->class(['comments']) }} id="comments" aria-labelledby="comments-title"
         data-comments data-comments-sort="{{ $ctx->sort }}" data-comments-preview="{{ route('comments.preview') }}">
    <header class="comments-head">
        <h2 class="comments-title" id="comments-title" data-comments-count>{{ match ($count) { 0 => 'No comments yet', 1 => '1 comment', default => number_format($count).' comments' } }}</h2>
        @if ($comments->total() > 1)
            <nav class="comments-sort" aria-label="Sort comments">
                @foreach (['oldest' => 'Oldest', 'newest' => 'Newest'] as $value => $label)
                    <a href="{{ request()->fullUrlWithQuery(['comments_sort' => $value, 'comments_page' => null, 'comments_reply' => null, 'comments_edit' => null]) }}#comments" @if ($ctx->sort === $value) aria-current="true" @endif data-comments-sort-link="{{ $value }}">{{ $label }}</a>
                @endforeach
            </nav>
        @endif
    </header>

    <p class="comments-flash" role="status" data-comments-flash @unless (session('comments.status')) hidden @endunless>{{ session('comments.status') }}</p>

    @if (! $ctx->open)
        <p class="comments-closed">Comments are closed.</p>
    @elseif ($ctx->canCreate)
        <div class="comments-compose">
            @php($me = $ctx->viewerModel())
            @include('comments::partials.avatar', [
                'url' => $me instanceof \Ruvelo\Comments\Contracts\CommentAuthor ? $me->commentAuthorAvatarUrl() : null,
                'initials' => \Ruvelo\Comments\Models\Comment::initialsOf(\Ruvelo\Comments\Models\Comment::nameOf($me)),
            ])
            @include('comments::partials.form', ['mode' => 'new', 'comment' => null])
        </div>
        <template data-comments-template>
            @include('comments::partials.form', ['mode' => 'reply', 'comment' => null])
        </template>
    @elseif ($ctx->viewer === null)
        <p class="comments-signin">
            @if (Route::has('login'))
                <a href="{{ route('login') }}">Sign in to comment</a> and join the conversation.
            @else
                Sign in to comment.
            @endif
        </p>
    @endif

    @if ($comments->isEmpty() && $comments->currentPage() === 1)
        <p class="comments-empty" data-comments-empty>{{ $ctx->canCreate ? 'Start the conversation: be the first to comment.' : 'Nobody has commented yet.' }}</p>
    @endif

    <ol class="comments-list" data-comments-list>
        @foreach ($comments as $comment)
            @include('comments::partials.comment')
        @endforeach
    </ol>

    @if ($comments->hasPages())
        <nav class="comments-pager" aria-label="More comments">
            @if ($comments->previousPageUrl())
                <a href="{{ $comments->previousPageUrl() }}" rel="prev">← {{ $ctx->sort === 'newest' ? 'Newer' : 'Earlier' }} comments</a>
            @else
                <span></span>
            @endif
            <span>Page {{ $comments->currentPage() }} of {{ $comments->lastPage() }}</span>
            @if ($comments->nextPageUrl())
                <a href="{{ $comments->nextPageUrl() }}" rel="next">{{ $ctx->sort === 'newest' ? 'Older' : 'Later' }} comments →</a>
            @else
                <span></span>
            @endif
        </nav>
    @endif
</section>

@once
    @include('comments::partials.script')
@endonce
