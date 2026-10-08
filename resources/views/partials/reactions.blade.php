{{-- The reaction chips under a comment, and the picker to add one. --}}
@php
    $summary = $comment->reactionSummary($ctx->viewerModel());
    $canReact = $ctx->canReact($comment);
    $mine = collect($summary)->where('reacted', true)->pluck('emoji')->all();
    $describe = function (array $reaction): string {
        $names = $reaction['names'];
        $shown = array_slice($names, 0, 8);
        $more = count($names) - count($shown);
        return implode(', ', $shown).($more > 0 ? " and {$more} more" : '').' reacted with '.$reaction['emoji'];
    };
@endphp
@if ($canReact)
    <form class="comments-reactions" method="post" action="{{ route('comments.react', $comment) }}" data-comments-form="react">
        @csrf
        @foreach ($summary as $reaction)
            <button type="submit" class="comments-reaction" name="emoji" value="{{ $reaction['emoji'] }}" aria-pressed="{{ $reaction['reacted'] ? 'true' : 'false' }}" title="{{ $describe($reaction) }}" aria-label="{{ $describe($reaction) }}. {{ $reaction['reacted'] ? 'Remove your reaction' : 'React with '.$reaction['emoji'] }}">
                <span class="comments-emoji" aria-hidden="true">{{ $reaction['emoji'] }}</span><span aria-hidden="true">{{ $reaction['count'] }}</span>
            </button>
        @endforeach
        <details class="comments-picker">
            <summary aria-label="Add a reaction" title="Add a reaction"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M17.2 9.2A7.5 7.5 0 1 1 10.8 2.6"/><path d="M7 12.2c.8 1 1.8 1.5 3 1.5s2.2-.5 3-1.5"/><circle cx="7.3" cy="8" r=".6" fill="currentColor"/><circle cx="12.7" cy="8" r=".6" fill="currentColor"/><path d="M16 1.5v5M13.5 4h5"/></svg></summary>
            <div class="comments-picker-panel" role="group" aria-label="Reactions">
                @foreach ($ctx->reactions as $emoji)
                    <button type="submit" name="emoji" value="{{ $emoji }}" aria-pressed="{{ in_array($emoji, $mine, true) ? 'true' : 'false' }}" aria-label="React with {{ $emoji }}" title="React with {{ $emoji }}">{{ $emoji }}</button>
                @endforeach
            </div>
        </details>
    </form>
@elseif ($summary !== [])
    <div class="comments-reactions">
        @foreach ($summary as $reaction)
            <span class="comments-reaction" title="{{ $describe($reaction) }}"><span class="comments-emoji" aria-hidden="true">{{ $reaction['emoji'] }}</span><span aria-hidden="true">{{ $reaction['count'] }}</span><span class="comments-hp">{{ $describe($reaction) }}</span></span>
        @endforeach
    </div>
@endif
