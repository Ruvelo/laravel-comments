<style>
    /* ruvelo/laravel-comments. Everything is scoped to .comments; override
       any --comments-* variable on .comments to re-theme. */
    .comments {
        --comments-bg: #ffffff;
        --comments-subtle: #f8f8fc;
        --comments-muted: #f0f0f7;
        --comments-line: #e4e4ef;
        --comments-ink: #16162a;
        --comments-text-2: #4b4b63;
        --comments-text-3: #74748b;
        --comments-accent: #3d4eff;
        --comments-accent-2: #a78bfa;
        --comments-accent-ink: #2b38d6;
        --comments-accent-soft: #eef0ff;
        --comments-on-accent: #ffffff;
        --comments-danger: #e5384f;
        --comments-danger-soft: #fdecef;
        --comments-warning: #b26a00;
        --comments-warning-soft: #fff4e0;
        --comments-success: #167a4a;
        --comments-radius: 8px;
        --comments-radius-lg: 14px;
        --comments-sans: "Geist", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        --comments-mono: "Geist Mono", ui-monospace, "SF Mono", "Cascadia Code", Menlo, Consolas, monospace;
        color-scheme: light;
    }
    @media (prefers-color-scheme: dark) {
        .comments:not([data-theme=light]) {
            --comments-bg: #11111c;
            --comments-subtle: #171725;
            --comments-muted: #1f1f30;
            --comments-line: #2a2a3f;
            --comments-ink: #f1f1f8;
            --comments-text-2: #b6b6cc;
            --comments-text-3: #8787a3;
            --comments-accent: #8f9bff;
            --comments-accent-2: #c4b5fd;
            --comments-accent-ink: #b3bbff;
            --comments-accent-soft: #1e2150;
            --comments-on-accent: #0b0b1a;
            --comments-danger: #ff6b80;
            --comments-danger-soft: #331520;
            --comments-warning: #ffc266;
            --comments-warning-soft: #2e2210;
            --comments-success: #74d6a2;
            color-scheme: dark;
        }
    }
    .comments[data-theme=dark] {
        --comments-bg: #11111c;
        --comments-subtle: #171725;
        --comments-muted: #1f1f30;
        --comments-line: #2a2a3f;
        --comments-ink: #f1f1f8;
        --comments-text-2: #b6b6cc;
        --comments-text-3: #8787a3;
        --comments-accent: #8f9bff;
        --comments-accent-2: #c4b5fd;
        --comments-accent-ink: #b3bbff;
        --comments-accent-soft: #1e2150;
        --comments-on-accent: #0b0b1a;
        --comments-danger: #ff6b80;
        --comments-danger-soft: #331520;
        --comments-warning: #ffc266;
        --comments-warning-soft: #2e2210;
        --comments-success: #74d6a2;
        color-scheme: dark;
    }

    .comments { color: var(--comments-ink); font: 15px/1.6 var(--comments-sans); -webkit-font-smoothing: antialiased; text-align: left; }
    .comments *, .comments *::before, .comments *::after { box-sizing: border-box; }
    .comments a { color: var(--comments-accent); text-decoration: none; }
    .comments a:hover { text-decoration: underline; text-underline-offset: 3px; }
    .comments :focus-visible { outline: 2px solid var(--comments-accent); outline-offset: 2px; border-radius: 4px; }
    .comments [hidden] { display: none !important; }
    .comments ol, .comments ul.comments-plain { list-style: none; margin: 0; padding: 0; }

    .comments-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem 1rem; margin: 0 0 1.25rem; }
    .comments-title { margin: 0; font-size: 1.25rem; line-height: 1.3; font-weight: 600; letter-spacing: -.02em; color: var(--comments-ink); }
    .comments-sort { display: inline-flex; gap: 2px; padding: 3px; background: var(--comments-muted); border-radius: var(--comments-radius); font-size: .8125rem; }
    .comments-sort a { padding: .25rem .7rem; border-radius: 6px; color: var(--comments-text-2); font-weight: 500; }
    .comments-sort a:hover { color: var(--comments-ink); text-decoration: none; }
    .comments-sort a[aria-current] { background: var(--comments-bg); color: var(--comments-ink); box-shadow: 0 0 0 1px var(--comments-line); }

    .comments-flash { display: flex; align-items: center; gap: .6rem; margin: 0 0 1.25rem; padding: .6rem .9rem; border: 1px solid var(--comments-line); border-radius: var(--comments-radius); background: var(--comments-subtle); font-size: .875rem; }
    .comments-flash::before { content: ""; flex: none; width: .5rem; height: .5rem; border-radius: 50%; background: var(--comments-accent); }

    .comments-avatar { display: grid; place-items: center; flex: none; width: 2.25rem; height: 2.25rem; border-radius: 50%; background: var(--comments-accent-soft); color: var(--comments-accent-ink); font-size: .75rem; font-weight: 600; letter-spacing: 0; object-fit: cover; user-select: none; }
    .comments-replies .comments-avatar { width: 1.75rem; height: 1.75rem; font-size: .65rem; }
    .comments-avatar--gone { background: var(--comments-muted); color: var(--comments-text-3); }

    /* The compose box */
    .comments-compose { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: .75rem; margin: 0 0 2rem; }
    .comments-compose > :not(.comments-avatar) { grid-column: 2; }
    .comments-form { min-width: 0; }
    .comments-box { border: 1px solid var(--comments-line); border-radius: var(--comments-radius-lg); background: var(--comments-bg); overflow: hidden; transition: border-color .15s, box-shadow .15s; }
    .comments-box:focus-within { border-color: var(--comments-accent); box-shadow: 0 0 0 3px var(--comments-accent-soft); }
    .comments-tabs { display: flex; gap: .25rem; padding: .4rem .5rem 0; border-bottom: 1px solid var(--comments-line); background: var(--comments-subtle); }
    .comments-tab { appearance: none; border: 0; background: none; margin: 0 0 -1px; padding: .45rem .75rem; font: 500 .8125rem/1 var(--comments-sans); color: var(--comments-text-3); cursor: pointer; border-radius: 6px 6px 0 0; border: 1px solid transparent; }
    .comments-tab:hover { color: var(--comments-ink); }
    .comments-tab[aria-selected=true] { background: var(--comments-bg); color: var(--comments-ink); border-color: var(--comments-line); border-bottom-color: var(--comments-bg); }
    .comments-box textarea { display: block; width: 100%; min-height: 5rem; margin: 0; padding: .75rem .9rem; border: 0; outline: 0; resize: vertical; background: transparent; color: inherit; font: .9375rem/1.6 var(--comments-sans); }
    .comments-box textarea::placeholder { color: var(--comments-text-3); }
    .comments-preview { min-height: 5rem; padding: .75rem .9rem; }
    .comments-preview-empty { color: var(--comments-text-3); font-style: italic; }
    .comments-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem .75rem; padding: .5rem .5rem .5rem .9rem; border-top: 1px solid var(--comments-line); background: var(--comments-subtle); }
    .comments-hint { margin: 0; font-size: .75rem; color: var(--comments-text-3); }
    .comments-hint code { font: .7rem var(--comments-mono); background: var(--comments-muted); padding: .1em .35em; border-radius: 4px; }
    .comments-buttons { display: flex; flex-wrap: wrap; gap: .4rem; margin-left: auto; }
    .comments-error { margin: .45rem 0 0; color: var(--comments-danger); font-size: .8125rem; }
    .comments-note { margin: .45rem 0 0; color: var(--comments-text-3); font-size: .8125rem; }
    .comments-hp { position: absolute !important; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }

    .comments-btn { display: inline-flex; align-items: center; gap: .4rem; margin: 0; padding: .5rem .9rem; border: 1px solid var(--comments-accent); border-radius: var(--comments-radius); background: var(--comments-accent); color: var(--comments-on-accent); font: 500 .8125rem/1 var(--comments-sans); cursor: pointer; box-shadow: 0 6px 16px -8px color-mix(in srgb, var(--comments-accent) 70%, transparent); text-decoration: none; }
    .comments-btn:hover { background: var(--comments-accent-ink); border-color: var(--comments-accent-ink); text-decoration: none; }
    .comments-btn[disabled] { opacity: .6; cursor: progress; }
    .comments-btn--quiet { background: var(--comments-bg); color: var(--comments-ink); border-color: var(--comments-line); box-shadow: none; }
    .comments-btn--quiet:hover { background: var(--comments-muted); border-color: var(--comments-line); }
    .comments-btn--danger { background: var(--comments-bg); color: var(--comments-danger); border-color: var(--comments-line); box-shadow: none; }
    .comments-btn--danger:hover { background: var(--comments-danger-soft); border-color: var(--comments-danger); }

    .comments-signin, .comments-closed, .comments-empty { margin: 0 0 2rem; padding: 1rem 1.1rem; border-radius: var(--comments-radius-lg); background: var(--comments-accent-soft); color: var(--comments-text-2); font-size: .9rem; }
    .comments-signin a { font-weight: 600; }
    .comments-empty { background: none; border: 1px dashed var(--comments-line); text-align: center; color: var(--comments-text-3); }

    /* One comment */
    .comments-list > .comments-item + .comments-item { margin-top: 1.5rem; }
    .comments-comment { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: .75rem; padding: .25rem .5rem .25rem 0; border-radius: var(--comments-radius-lg); scroll-margin-top: 5rem; }
    .comments-item:target > .comments-comment { background: linear-gradient(90deg, var(--comments-accent-soft), transparent 85%); padding-left: .5rem; margin-left: -.5rem; }
    .comments-meta { display: flex; flex-wrap: wrap; align-items: baseline; gap: .15rem .5rem; font-size: .8125rem; color: var(--comments-text-3); line-height: 1.4; padding-top: .1rem; }
    .comments-author { color: var(--comments-ink); font-weight: 600; font-size: .875rem; }
    .comments-meta a { color: inherit; }
    .comments-meta a:hover { color: var(--comments-accent); }
    .comments-chip { display: inline-block; padding: .05rem .5rem; border-radius: 999px; background: var(--comments-accent-soft); color: var(--comments-accent-ink); font-size: .7rem; font-weight: 600; }
    .comments-chip--pending { background: var(--comments-warning-soft); color: var(--comments-warning); }
    .comments-body { margin: .25rem 0 0; }
    .comments-gone { margin: .1rem 0 0; color: var(--comments-text-3); font-style: italic; font-size: .875rem; }
    .comments-comment.is-pending .comments-body { opacity: .8; }

    .comments-prose { font-size: .9375rem; line-height: 1.65; overflow-wrap: anywhere; color: var(--comments-ink); }
    .comments-prose > :first-child { margin-top: 0; }
    .comments-prose > :last-child { margin-bottom: 0; }
    .comments-prose p, .comments-prose ul, .comments-prose ol, .comments-prose pre, .comments-prose blockquote, .comments-prose table { margin: .6rem 0; }
    .comments-prose ul, .comments-prose ol { padding-left: 1.4rem; list-style: revert; }
    .comments-prose h1, .comments-prose h2, .comments-prose h3, .comments-prose h4 { font-size: 1rem; font-weight: 600; letter-spacing: -.01em; margin: 1rem 0 .4rem; }
    .comments-prose a { text-decoration: underline; text-decoration-color: color-mix(in srgb, currentColor 35%, transparent); text-underline-offset: 3px; }
    .comments-prose a:hover { text-decoration-color: currentColor; }
    .comments-prose code { font: .85em var(--comments-mono); background: var(--comments-muted); padding: .12em .4em; border-radius: 5px; }
    .comments-prose pre { background: var(--comments-subtle); border: 1px solid var(--comments-line); border-radius: var(--comments-radius); padding: .7rem .9rem; overflow-x: auto; line-height: 1.55; }
    .comments-prose pre code { background: none; padding: 0; font-size: .8125rem; }
    .comments-prose blockquote { padding: .15rem .9rem; border-left: 3px solid var(--comments-line); color: var(--comments-text-2); }
    .comments-prose table { border-collapse: collapse; display: block; overflow-x: auto; font-size: .875rem; }
    .comments-prose th, .comments-prose td { border: 1px solid var(--comments-line); padding: .3rem .6rem; }
    .comments-prose th { background: var(--comments-subtle); font-weight: 500; }
    .comments-prose .comments-mention { font-weight: 600; color: var(--comments-accent-ink); text-decoration: none; background: var(--comments-accent-soft); border-radius: 4px; padding: 0 .2em; }
    .comments-prose input[type=checkbox] { accent-color: var(--comments-accent); margin-right: .3rem; }

    .comments-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem .25rem; margin-top: .4rem; }
    .comments .comments-action { appearance: none; display: inline-flex; align-items: center; margin: 0; padding: .25rem .5rem; border: 0; border-radius: 6px; background: none; color: var(--comments-text-3); font: 500 .8125rem/1.2 var(--comments-sans); cursor: pointer; }
    .comments .comments-action:hover { background: var(--comments-muted); color: var(--comments-ink); text-decoration: none; }
    .comments-action--danger:hover { color: var(--comments-danger) !important; background: var(--comments-danger-soft) !important; }
    .comments-actions form { display: contents; }

    .comments-reactions { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; margin-right: .35rem; }
    .comments-reaction { appearance: none; display: inline-flex; align-items: center; gap: .3rem; margin: 0; padding: .15rem .55rem; border: 1px solid var(--comments-line); border-radius: 999px; background: var(--comments-bg); color: var(--comments-text-2); font: 500 .8125rem/1.4 var(--comments-sans); cursor: pointer; }
    span.comments-reaction { cursor: default; }
    button.comments-reaction:hover { border-color: var(--comments-accent); }
    .comments-reaction[aria-pressed=true] { background: var(--comments-accent-soft); border-color: color-mix(in srgb, var(--comments-accent) 55%, transparent); color: var(--comments-accent-ink); }
    .comments-emoji, .comments-picker-panel button { font-family: "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", sans-serif; }
    .comments-emoji { font-size: .95rem; line-height: 1; }
    .comments-picker { position: relative; }
    .comments-picker > summary { list-style: none; display: inline-flex; align-items: center; justify-content: center; width: 2rem; height: 1.6rem; border: 1px solid var(--comments-line); border-radius: 999px; color: var(--comments-text-3); cursor: pointer; }
    .comments-picker > summary::-webkit-details-marker { display: none; }
    .comments-picker > summary:hover, .comments-picker[open] > summary { color: var(--comments-accent); border-color: var(--comments-accent); }
    .comments-picker svg { width: 1rem; height: 1rem; }
    .comments-picker-panel { position: absolute; z-index: 5; bottom: calc(100% + .35rem); left: 0; display: flex; gap: .15rem; padding: .3rem; border: 1px solid var(--comments-line); border-radius: var(--comments-radius-lg); background: var(--comments-bg); box-shadow: 0 16px 40px -24px color-mix(in srgb, var(--comments-accent) 55%, transparent); }
    .comments-picker-panel button { appearance: none; margin: 0; padding: .3rem .4rem; border: 0; border-radius: var(--comments-radius); background: none; font-size: 1.15rem; line-height: 1; cursor: pointer; }
    .comments-picker-panel button:hover, .comments-picker-panel button[aria-pressed=true] { background: var(--comments-accent-soft); }

    .comments-replies { margin: .75rem 0 0 1.1rem; padding-left: 1.4rem; border-left: 2px solid var(--comments-line); }
    .comments-replies > .comments-item + .comments-item { margin-top: 1rem; }
    .comments-inline { margin: .75rem 0 0; }
    .comments-replies .comments-inline, .comments-item > .comments-inline { margin-left: 3rem; }
    .comments-comment .comments-inline { margin-left: 0; }

    .comments-pager { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--comments-line); font-size: .875rem; color: var(--comments-text-3); }
    .comments-pager a { font-weight: 500; }

    @media (min-width: 40rem) {
        .comments-avatar { width: 2.5rem; height: 2.5rem; font-size: .8rem; }
        .comments-replies { margin-left: 1.25rem; padding-left: 1.6rem; }
    }
    @media (prefers-reduced-motion: no-preference) {
        .comments-item.is-new > .comments-comment { animation: comments-in .5s ease-out; }
        @keyframes comments-in { from { background: var(--comments-accent-soft); } }
    }
</style>
