{{-- A fake Halyard app page hosting the thread: what the component looks like in context. --}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $entry->title }} · Halyard changelog</title>
    <style>
        :root { --h-bg: #fff; --h-subtle: #f8f8fc; --h-line: #e4e4ef; --h-ink: #16162a; --h-text-2: #4b4b63; --h-text-3: #74748b; --h-accent: #3d4eff; --h-accent-2: #a78bfa; --h-soft: #eef0ff; --h-accent-ink: #2b38d6; --h-ok: #167a4a; --h-ok-soft: #e8f8ef; color-scheme: light; }
        @media (prefers-color-scheme: dark) { :root { --h-bg: #11111c; --h-subtle: #171725; --h-line: #2a2a3f; --h-ink: #f1f1f8; --h-text-2: #b6b6cc; --h-text-3: #8787a3; --h-accent: #8f9bff; --h-accent-2: #c4b5fd; --h-soft: #1e2150; --h-accent-ink: #b3bbff; --h-ok: #74d6a2; --h-ok-soft: #0f2b1f; color-scheme: dark; } }
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; background: var(--h-bg); color: var(--h-ink); font: 15px/1.6 "Geist", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; -webkit-font-smoothing: antialiased; }
        body::before { content: ""; position: fixed; inset: 0 0 auto; height: 3px; z-index: 20; background: linear-gradient(90deg, var(--h-accent), var(--h-accent-2)); }
        a { color: var(--h-accent); text-decoration: none; }
        .bar { display: flex; align-items: center; gap: 1.5rem; padding: .8rem max(16px, calc((100% - 72rem) / 2)); border-bottom: 1px solid var(--h-line); }
        .brand { display: inline-flex; align-items: center; gap: .6rem; font-weight: 600; color: var(--h-ink); letter-spacing: -.01em; }
        .mark { display: grid; place-items: center; width: 1.75rem; height: 1.75rem; border-radius: 7px; background: linear-gradient(135deg, var(--h-accent), var(--h-accent-2)); color: #fff; font-size: .85rem; font-weight: 700; }
        .bar nav { display: flex; gap: 1.25rem; font-size: .9rem; overflow-x: auto; }
        .bar nav a { color: var(--h-text-2); white-space: nowrap; }
        .bar nav a[aria-current] { color: var(--h-ink); font-weight: 500; }
        .me { margin-left: auto; display: grid; place-items: center; flex: none; width: 2rem; height: 2rem; border-radius: 50%; background: var(--h-soft); color: var(--h-accent-ink); font-size: .7rem; font-weight: 600; }
        main { max-width: 46rem; margin: 0 auto; padding: 2.5rem 16px 5rem; }
        .crumbs { font-size: .85rem; color: var(--h-text-3); margin: 0 0 1rem; }
        .crumbs a { color: var(--h-text-3); }
        h1 { font-size: 2.1rem; line-height: 1.15; letter-spacing: -.03em; font-weight: 600; margin: 0 0 .9rem; }
        .meta { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .75rem; font-size: .85rem; color: var(--h-text-3); margin-bottom: 2rem; }
        .chip { padding: .1rem .6rem; border-radius: 999px; background: var(--h-ok-soft); color: var(--h-ok); font-weight: 600; font-size: .75rem; }
        .prose { font-size: 1rem; line-height: 1.75; color: var(--h-text-2); }
        .prose strong { color: var(--h-ink); }
        .prose code { font: .85em "Geist Mono", ui-monospace, Menlo, Consolas, monospace; background: var(--h-subtle); border: 1px solid var(--h-line); padding: .1em .35em; border-radius: 5px; }
        .meter { display: grid; gap: .65rem; margin: 1.75rem 0; padding: 1.1rem 1.25rem; border: 1px solid var(--h-line); border-radius: 14px; background: var(--h-subtle); }
        .meter-row { display: grid; grid-template-columns: 7.5rem 1fr 4.5rem; align-items: center; gap: .9rem; font-size: .85rem; }
        .meter-row span:first-child { color: var(--h-text-2); }
        .track { height: .5rem; border-radius: 999px; background: var(--h-line); overflow: hidden; }
        .track i { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, var(--h-accent), var(--h-accent-2)); }
        .meter-row b { text-align: right; font-weight: 600; font-variant-numeric: tabular-nums; }
        hr { border: 0; border-top: 1px solid var(--h-line); margin: 3rem 0 2.25rem; }
        @media (max-width: 40rem) { .bar nav { display: none; } h1 { font-size: 1.7rem; } .meter-row { grid-template-columns: 5.5rem 1fr 4rem; } }
    </style>
</head>
<body>
    <header class="bar">
        <a class="brand" href="#"><span class="mark" aria-hidden="true">H</span>Halyard</a>
        <nav aria-label="Halyard">
            <a href="#">Dashboard</a><a href="#">Invoices</a><a href="#">Customers</a><a href="#">Plans</a><a href="#" aria-current="page">Changelog</a>
        </nav>
        <span class="me" title="Signed in as Kenji Mori" aria-hidden="true">KM</span>
    </header>
    <main>
        <p class="crumbs"><a href="#">Changelog</a> / October 2026</p>
        <h1>{{ $entry->title }}</h1>
        <p class="meta"><span class="chip">New</span><span>Shipped 3 days ago by Maya Okafor</span></p>

        @unless ($compact ?? false)
            <div class="prose">
                <p>Charge for what your customers actually use. <strong>Usage-based billing</strong> lets you attach a meter to any price: API calls, seats, gigabytes or anything else you count. Halyard totals the events, rates them and puts them on the next invoice.</p>
                <div class="meter" aria-label="Example: usage this month">
                    <div class="meter-row"><span>API calls</span><span class="track"><i style="width: 72%"></i></span><b>1.44M</b></div>
                    <div class="meter-row"><span>Seats</span><span class="track"><i style="width: 38%"></i></span><b>38</b></div>
                    <div class="meter-row"><span>Storage</span><span class="track"><i style="width: 55%"></i></span><b>212 GB</b></div>
                </div>
                <p>Send events with <code>POST /v2/usage</code>, or let our Stripe sync bring them in. Invoices now show projected usage before the period closes, so nobody is surprised.</p>
            </div>
        @endunless

        <hr>
        <x-comments::thread :for="$entry" />
    </main>
</body>
</html>
