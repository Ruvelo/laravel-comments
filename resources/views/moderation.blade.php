@extends(config('comments.layout') ?? 'comments::layout')

@section('title', 'Moderation')

@section(config('comments.layout_section', 'content'))
    @once
        @unless (config('comments.layout') === null)
            @include('comments::partials.styles')
        @endunless
    @endonce
    <div class="comments comments-moderation">
        <style>
            .comments-moderation .comments-tabs-nav { display: flex; flex-wrap: wrap; gap: .25rem; margin: 0 0 1.5rem; border-bottom: 1px solid var(--comments-line); }
            .comments-moderation .comments-tabs-nav a { display: inline-flex; align-items: center; gap: .5rem; margin-bottom: -1px; padding: .6rem .85rem; border-bottom: 2px solid transparent; color: var(--comments-text-2); font-weight: 500; font-size: .9rem; }
            .comments-moderation .comments-tabs-nav a:hover { color: var(--comments-ink); text-decoration: none; }
            .comments-moderation .comments-tabs-nav a[aria-current] { color: var(--comments-accent-ink); border-bottom-color: var(--comments-accent); }
            .comments-moderation .comments-count { min-width: 1.4rem; padding: .05rem .45rem; border-radius: 999px; background: var(--comments-muted); color: var(--comments-text-2); font-size: .75rem; text-align: center; }
            .comments-moderation a[aria-current] .comments-count { background: var(--comments-accent-soft); color: var(--comments-accent-ink); }
            .comments-queue > li { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: .85rem; padding: 1.1rem 0; border-bottom: 1px solid var(--comments-line); }
            .comments-queue > li:first-child { padding-top: .25rem; }
            .comments-where { margin: .35rem 0 0; font-size: .8125rem; color: var(--comments-text-3); }
            .comments-where a { font-weight: 500; }
            .comments-reply-to { margin: .5rem 0 0; padding: .45rem .75rem; border-left: 3px solid var(--comments-line); border-radius: 0 var(--comments-radius) var(--comments-radius) 0; background: var(--comments-subtle); color: var(--comments-text-2); font-size: .8125rem; }
            .comments-decide { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .75rem; }
            .comments-decide form { margin: 0; }
        </style>

        <h1>Moderation</h1>
        <p class="comments-lede">{{ $counts['pending'] === 0 ? 'Nothing is waiting. New comments that need approval show up here.' : 'Approve comments to publish them, or reject them to delete them.' }}</p>

        @if (session('comments.status'))
            <p class="comments-flash" role="status">{{ session('comments.status') }}</p>
        @endif

        <nav class="comments-tabs-nav" aria-label="Comment status">
            @foreach ($statuses as $key => $label)
                <a href="{{ route('comments.moderation', $key === 'pending' ? [] : ['status' => $key]) }}" @if ($status === $key) aria-current="page" @endif>
                    {{ $label }} <span class="comments-count">{{ number_format($counts[$key]) }}</span>
                </a>
            @endforeach
        </nav>

        @if ($comments->isEmpty())
            <p class="comments-empty">{{ match ($status) { 'approved' => 'No approved comments yet.', 'deleted' => 'No deleted comments.', default => 'You’re all caught up. Nothing is waiting for approval.' } }}</p>
        @else
            <ol class="comments-queue">
                @foreach ($comments as $comment)
                    @php
                        $commentable = $comment->commentable;
                        $where = \Ruvelo\Comments\Support\Commentables::call($commentable, 'commentTitle');
                        $url = \Ruvelo\Comments\Support\Commentables::call($commentable, 'commentUrl');
                    @endphp
                    <li id="{{ $comment->anchor() }}">
                        @include('comments::partials.avatar', ['url' => $comment->authorAvatarUrl(), 'initials' => $comment->authorInitials()])
                        <div>
                            <header class="comments-meta">
                                <span class="comments-author">{{ $comment->authorName() }}</span>
                                <time datetime="{{ $comment->created_at->toIso8601String() }}" title="{{ $comment->created_at->toDayDateTimeString() }}">{{ $comment->created_at->diffForHumans() }}</time>
                                @if ($comment->trashed())
                                    <span class="comments-chip comments-chip--pending">Deleted {{ $comment->deleted_at?->diffForHumans() }}</span>
                                @elseif ($comment->isPending())
                                    <span class="comments-chip comments-chip--pending">Waiting</span>
                                @endif
                            </header>
                            <p class="comments-where">
                                {{ $comment->isReply() ? 'Reply' : 'Comment' }} on
                                @if (is_string($url) && $url !== '')
                                    <a href="{{ $url }}#{{ $comment->anchor() }}">{{ is_string($where) ? $where : 'this page' }}</a>
                                @else
                                    <strong>{{ is_string($where) ? $where : 'a deleted item' }}</strong>
                                @endif
                            </p>
                            @if ($comment->parent && ! $comment->parent->trashed())
                                <p class="comments-reply-to">Replying to {{ $comment->parent->authorName() }}: “{{ $comment->parent->excerpt(120) }}”</p>
                            @endif
                            <div class="comments-body comments-prose">{!! $comment->html !!}</div>
                            @unless ($comment->trashed())
                                <div class="comments-decide">
                                    @if ($comment->isPending())
                                        <form method="post" action="{{ route('comments.approve', $comment) }}">
                                            @csrf
                                            <button type="submit" class="comments-btn">Approve</button>
                                        </form>
                                    @endif
                                    <form method="post" action="{{ route('comments.destroy', $comment) }}">
                                        @csrf
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button type="submit" class="comments-btn comments-btn--danger">{{ $comment->isPending() ? 'Reject' : 'Delete' }}</button>
                                    </form>
                                </div>
                            @endunless
                        </div>
                    </li>
                @endforeach
            </ol>

            @if ($comments->hasPages())
                <nav class="comments-pager" aria-label="Pages">
                    @if ($comments->previousPageUrl()) <a href="{{ $comments->previousPageUrl() }}" rel="prev">← Previous</a> @else <span></span> @endif
                    <span>Page {{ $comments->currentPage() }} of {{ $comments->lastPage() }}</span>
                    @if ($comments->nextPageUrl()) <a href="{{ $comments->nextPageUrl() }}" rel="next">Next →</a> @else <span></span> @endif
                </nav>
            @endif
        @endif
    </div>
@endsection
