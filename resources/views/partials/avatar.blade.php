{{-- A person's avatar: their image, or their initials. --}}
@if ($url ?? null)
    <img class="comments-avatar" src="{{ $url }}" alt="" loading="lazy">
@else
    <span class="comments-avatar" aria-hidden="true">{{ $initials }}</span>
@endif
