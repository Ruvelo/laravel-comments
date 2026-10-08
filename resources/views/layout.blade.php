<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ config('app.name') }}</title>
    @include('comments::partials.styles')
    <style>
        body { margin: 0; background: #fff; }
        @media (prefers-color-scheme: dark) { body { background: #11111c; } }
        body::before { content: ""; position: fixed; inset: 0 0 auto; height: 3px; z-index: 20; background: linear-gradient(90deg, #3d4eff, #a78bfa); }
        .comments-page { min-height: 100vh; background: var(--comments-bg); }
        .comments-bar { display: flex; align-items: center; gap: .6rem; padding: .85rem max(16px, calc((100% - 60rem) / 2)); border-bottom: 1px solid var(--comments-line); font-weight: 600; font-size: .95rem; letter-spacing: -.01em; }
        .comments-mark { display: grid; place-items: center; width: 1.75rem; height: 1.75rem; border-radius: 7px; background: linear-gradient(135deg, var(--comments-accent), var(--comments-accent-2)); color: #fff; font-size: .85rem; font-weight: 700; box-shadow: 0 4px 12px -4px color-mix(in srgb, var(--comments-accent) 60%, transparent); }
        .comments-bar a { color: var(--comments-ink); }
        .comments-bar .comments-crumb { color: var(--comments-text-3); font-weight: 500; }
        .comments-shell { max-width: 60rem; margin: 0 auto; padding: 2rem 16px 4rem; }
        .comments-shell h1 { margin: 0 0 .35rem; font-size: 1.75rem; line-height: 1.2; font-weight: 600; letter-spacing: -.02em; }
        .comments-lede { margin: 0 0 1.75rem; color: var(--comments-text-2); }
    </style>
    @stack('comments-head')
</head>
<body>
    <div class="comments comments-page">
        <header class="comments-bar">
            <span class="comments-mark" aria-hidden="true">{{ mb_strtoupper(mb_substr((string) config('app.name'), 0, 1)) }}</span>
            <a href="{{ url('/') }}">{{ config('app.name') }}</a>
            <span class="comments-crumb">/ Comments</span>
        </header>
        <main class="comments-shell">
            @yield('content')
        </main>
    </div>
</body>
</html>
