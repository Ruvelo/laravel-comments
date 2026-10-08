// The static demo has no server: reactions, sorting and the preview run
// here instead, and posting explains that the demo is read-only. These
// listeners run in the capture phase, before the package's own script.
(() => {
    const viewer = (window.COMMENTS_DEMO || {}).viewer || 'You';
    const readOnly = 'This is a read-only demo: install ruvelo/laravel-comments to post, edit and delete.';

    const escape = (text) => text.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // A small Markdown subset, enough to preview a comment.
    const markdown = (source) => {
        const blocks = [];
        source = source.replace(/```(\w*)\n([\s\S]*?)```/g, (_, lang, code) => {
            blocks.push('<pre><code>' + escape(code.replace(/\n$/, '')) + '</code></pre>');
            return '\u0000' + (blocks.length - 1) + '\u0000';
        });
        const inline = (text) => escape(text)
            .replace(/`([^`]+)`/g, '<code>$1</code>')
            .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
            .replace(/(^|[^*])\*([^*]+)\*/g, '$1<em>$2</em>')
            .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" rel="nofollow">$1</a>')
            .replace(/(^|[^\w@])@(maya|tom|ines|kenji|sam|lena|ravi|ana|jonas)\b/gi, '$1<span class="comments-mention">@$2</span>');
        return source.split(/\n{2,}/).map((part) => {
            part = part.trim();
            if (!part) return '';
            const block = part.match(/^\u0000(\d+)\u0000$/);
            if (block) return blocks[block[1]];
            if (/^([-*] )/.test(part)) return '<ul>' + part.split('\n').map((line) => '<li>' + inline(line.replace(/^[-*] /, '')) + '</li>').join('') + '</ul>';
            if (/^> /.test(part)) return '<blockquote><p>' + inline(part.replace(/^> ?/gm, '')) + '</p></blockquote>';
            return '<p>' + inline(part).replace(/\n/g, '<br>') + '</p>';
        }).join('\n').replace(/\u0000(\d+)\u0000/g, (_, i) => blocks[i]);
    };

    const describe = (names, emoji) => names.join(', ') + ' reacted with ' + emoji;

    const react = (form, emoji) => {
        let chip = [...form.querySelectorAll('.comments-reaction')].find((button) => button.value === emoji);
        const picker = form.querySelector('.comments-picker');
        if (!chip) {
            chip = document.createElement('button');
            chip.type = 'submit';
            chip.className = 'comments-reaction';
            chip.name = 'emoji';
            chip.value = emoji;
            chip.dataset.names = '[]';
            chip.innerHTML = '<span class="comments-emoji" aria-hidden="true">' + emoji + '</span><span aria-hidden="true">0</span>';
            form.insertBefore(chip, picker);
        }
        const title = chip.getAttribute('title') || '';
        let names = chip.dataset.names ? JSON.parse(chip.dataset.names) : title.replace(/ reacted with .*$/, '').split(', ').filter(Boolean);
        const pressed = chip.getAttribute('aria-pressed') === 'true';
        names = pressed ? names.filter((name) => name !== viewer) : [...names, viewer];
        chip.dataset.names = JSON.stringify(names);
        if (!names.length) { chip.remove(); }
        else {
            chip.setAttribute('aria-pressed', String(!pressed));
            chip.title = describe(names, emoji);
            chip.setAttribute('aria-label', chip.title);
            chip.lastElementChild.textContent = names.length;
        }
        form.querySelectorAll('.comments-picker-panel button').forEach((button) => {
            if (button.value === emoji) button.setAttribute('aria-pressed', String(!pressed));
        });
        if (picker) picker.open = false;
    };

    const flash = (form, message) => {
        const slot = form.querySelector('[data-comments-error]');
        if (slot) { slot.textContent = message; slot.hidden = false; return; }
        const root = form.closest('[data-comments]');
        const top = root?.querySelector('[data-comments-flash]');
        if (top) { top.textContent = message; top.hidden = false; top.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); return; }
        let note = document.querySelector('[data-demo-note]');
        if (!note) {
            note = document.createElement('p');
            note.className = 'comments-flash';
            note.setAttribute('role', 'status');
            note.dataset.demoNote = '';
            document.querySelector('.comments-tabs-nav')?.before(note);
        }
        note.textContent = 'This is a read-only demo: install ruvelo/laravel-comments to approve and reject comments.';
    };

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form');
        if (!form || !form.closest('.comments')) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        if (form.dataset.commentsForm === 'react') {
            const emoji = event.submitter?.value;
            if (emoji) react(form, emoji);
            return;
        }
        flash(form, readOnly);
    }, true);

    document.addEventListener('click', (event) => {
        const tab = event.target.closest('[data-comments-tab="preview"]');
        if (tab) {
            event.preventDefault();
            event.stopImmediatePropagation();
            const form = tab.closest('form');
            const textarea = form.querySelector('textarea');
            const pane = form.querySelector('[data-comments-preview-pane]');
            form.querySelectorAll('[data-comments-tab]').forEach((button) => button.setAttribute('aria-selected', String(button === tab)));
            pane.innerHTML = textarea.value.trim() ? markdown(textarea.value) : '<p class="comments-preview-empty">Nothing to preview yet.</p>';
            textarea.hidden = true;
            pane.hidden = false;
            return;
        }

        const sort = event.target.closest('[data-comments-sort-link]');
        if (sort) {
            event.preventDefault();
            event.stopImmediatePropagation();
            const root = sort.closest('[data-comments]');
            if (root.dataset.commentsSort === sort.dataset.commentsSortLink) return;
            root.dataset.commentsSort = sort.dataset.commentsSortLink;
            root.querySelectorAll('[data-comments-sort-link]').forEach((link) => link.toggleAttribute('aria-current', link === sort));
            const list = root.querySelector('[data-comments-list]');
            [...list.children].reverse().forEach((item) => list.append(item));
            return;
        }

        // Links that need a server (moderation tabs work: they're static pages).
        const link = event.target.closest('.comments a[href*="?comments_"]');
        if (link && !link.matches('[data-comments-reply], [data-comments-edit]')) event.preventDefault();
    }, true);

    // Reply and edit forms prefill the demo with something to preview.
    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-comments-reply]');
        if (!opener) return;
        setTimeout(() => {
            const textarea = opener.closest('[data-comment]')?.querySelector(':scope > form textarea');
            if (textarea && !textarea.value) textarea.value = 'Thanks! **Backfill** would save us a week. `meter: api_calls` is the one we need.';
        });
    });
})();
