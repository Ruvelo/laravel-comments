{{--
    The comment form: new comments, replies and edits.
    $mode: new | reply | edit. $comment: the comment replied to or edited.
--}}
@php
    $formId = match ($mode) { 'reply' => 'reply-'.$comment?->id, 'edit' => 'edit-'.$comment?->id, default => 'new' };
    $isCurrent = old('_comments_form') === $formId;
    $bag = session('errors')?->getBag('comments');
    $error = $isCurrent ? $bag?->first() : null;
    $preview = $isCurrent ? session('comments.preview') : null;
    $value = $isCurrent ? old('body') : ($mode === 'edit' ? $comment?->body : '');
    $honeypot = config('comments.honeypot');
    $label = match ($mode) { 'reply' => 'Write a reply', 'edit' => 'Edit your comment', default => 'Write a comment' };
@endphp
<form class="comments-form {{ $mode === 'new' ? '' : 'comments-inline' }}" id="{{ $formId }}" method="post"
      action="{{ $mode === 'edit' ? route('comments.update', $comment) : $ctx->storeUrl() }}"
      data-comments-form="{{ $mode === 'edit' ? 'update' : 'create' }}">
    @csrf
    @if ($mode === 'edit') <input type="hidden" name="_method" value="PATCH"> @endif
    <input type="hidden" name="_comments_form" value="{{ $formId }}">
    @if ($mode === 'reply') <input type="hidden" name="parent_id" value="{{ $comment?->id }}"> @endif
    @if (is_string($honeypot) && $honeypot !== '')
        <div class="comments-hp" aria-hidden="true"><label>Leave this empty <input type="text" name="{{ $honeypot }}" tabindex="-1" autocomplete="off"></label></div>
    @endif

    <div class="comments-box">
        <div class="comments-tabs" role="tablist" aria-label="Editor mode" data-comments-tabs hidden>
            <button type="button" class="comments-tab" role="tab" aria-selected="true" data-comments-tab="write">Write</button>
            <button type="button" class="comments-tab" role="tab" aria-selected="false" data-comments-tab="preview">Preview</button>
        </div>
        <label class="comments-hp" for="{{ $formId }}-body">{{ $label }}</label>
        <textarea id="{{ $formId }}-body" name="body" rows="3" maxlength="{{ config('comments.max_length', 5000) }}"
                  placeholder="{{ $mode === 'reply' ? 'Write a reply…' : 'Add to the conversation…' }}" required
                  @if ($error) aria-invalid="true" aria-describedby="{{ $formId }}-error" @endif
                  @if ($isCurrent || $mode !== 'new') autofocus @endif>{{ $value }}</textarea>
        <div class="comments-preview comments-prose" data-comments-preview-pane aria-live="polite" @if (! $preview) hidden @endif>{!! $preview !!}</div>
        <div class="comments-foot">
            <p class="comments-hint">Markdown works: <code>**bold**</code> <code>`code`</code> <code>[link](url)</code>@if (config('comments.mentions.resolver')) <code>@name</code>@endif</p>
            <div class="comments-buttons">
                @if ($mode !== 'new')
                    <a class="comments-btn comments-btn--quiet" href="{{ request()->fullUrlWithQuery(['comments_reply' => null, 'comments_edit' => null]) }}#{{ $comment?->anchor() }}" data-comments-cancel>Cancel</a>
                @endif
                <button type="submit" class="comments-btn comments-btn--quiet" name="action" value="preview" data-comments-nojs>Preview</button>
                <button type="submit" class="comments-btn">{{ match ($mode) { 'reply' => 'Reply', 'edit' => 'Save changes', default => 'Post comment' } }}</button>
            </div>
        </div>
    </div>
    <p class="comments-error" id="{{ $formId }}-error" role="alert" data-comments-error @if (! $error) hidden @endif>{{ $error }}</p>
</form>
