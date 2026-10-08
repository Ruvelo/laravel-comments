<script>
    // ruvelo/laravel-comments: everything works as plain forms; this posts,
    // edits, deletes, reacts and previews in place instead of reloading.
    (() => {
        if (window.RuveloComments) return;
        window.RuveloComments = { version: 1 };

        const headers = (form) => ({
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': form.querySelector('[name=_token]')?.value || document.querySelector('meta[name=csrf-token]')?.content || '',
        });
        const fragment = (html) => {
            const template = document.createElement('template');
            template.innerHTML = (html || '').trim();
            return template.content.firstElementChild;
        };
        const enhance = (scope) => {
            scope.querySelectorAll('[data-comments-tabs]').forEach((tabs) => { tabs.hidden = false; });
            scope.querySelectorAll('[data-comments-nojs]').forEach((button) => { button.hidden = true; });
        };
        const setError = (form, message) => {
            const slot = form.querySelector('[data-comments-error]');
            if (slot) { slot.textContent = message || ''; slot.hidden = !message; }
            else if (message) alert(message);
        };
        const flash = (root, message) => {
            const slot = root.querySelector('[data-comments-flash]');
            if (!slot) return;
            slot.textContent = message || '';
            slot.hidden = !message;
        };
        const setCount = (root, count) => {
            if (typeof count !== 'number') return;
            root.querySelector('[data-comments-count]').textContent =
                count === 0 ? 'No comments yet' : count === 1 ? '1 comment' : count.toLocaleString('en') + ' comments';
            if (count > 0) root.querySelector('[data-comments-empty]')?.remove();
        };
        const errorFrom = (response, data) => {
            if (data.errors) return Object.values(data.errors)[0][0];
            if (response.status === 401) return 'Sign in to comment.';
            if (response.status === 419) return 'Your session expired. Reload the page and try again.';
            return data.message || 'Something went wrong. Try again.';
        };

        document.querySelectorAll('[data-comments]').forEach(enhance);

        // --- Write / Preview ---------------------------------------------
        const showTab = async (form, tab) => {
            const textarea = form.querySelector('textarea');
            const pane = form.querySelector('[data-comments-preview-pane]');
            form.querySelectorAll('[data-comments-tab]').forEach((button) => {
                button.setAttribute('aria-selected', String(button.dataset.commentsTab === tab));
            });
            textarea.hidden = tab === 'preview';
            pane.hidden = tab !== 'preview';
            if (tab === 'write') { textarea.focus(); return; }

            if (!textarea.value.trim()) {
                pane.innerHTML = '<p class="comments-preview-empty">Nothing to preview yet.</p>';
                return;
            }
            pane.innerHTML = '<p class="comments-preview-empty">Rendering preview…</p>';
            const root = form.closest('[data-comments]');
            try {
                const response = await fetch(root.dataset.commentsPreview, {
                    method: 'POST', headers: headers(form), body: new URLSearchParams({ body: textarea.value }),
                });
                const data = await response.json().catch(() => ({}));
                pane.innerHTML = response.ok ? (data.html || '') : '<p class="comments-preview-empty">The preview didn’t load. Try again.</p>';
            } catch {
                pane.innerHTML = '<p class="comments-preview-empty">The preview didn’t load. Check your connection.</p>';
            }
        };

        // --- Inline reply and edit forms ----------------------------------
        const inlineForm = (root, kind, comment) => {
            const form = root.querySelector('template[data-comments-template]')?.content.firstElementChild?.cloneNode(true);
            if (!form) return null;
            const id = kind + '-' + comment;
            form.id = id;
            form.querySelector('[name=_comments_form]').value = id;
            const textarea = form.querySelector('textarea');
            textarea.id = id + '-body';
            form.querySelector('label[for]')?.setAttribute('for', textarea.id);
            form.querySelector('[data-comments-error]').id = id + '-error';
            enhance(form);
            return form;
        };

        const openReply = (link) => {
            const item = link.closest('[data-comment]');
            const open = item.querySelector(':scope > form.comments-inline');
            if (open) { open.querySelector('textarea').focus(); return; }
            const form = inlineForm(link.closest('[data-comments]'), 'reply', item.dataset.comment);
            if (!form) return;
            form.querySelector('[name=parent_id]').value = item.dataset.comment;
            item.querySelector(':scope > [data-comments-card]').after(form);
            form.querySelector('textarea').focus();
        };

        const openEdit = (link) => {
            const card = link.closest('[data-comments-card]');
            const item = link.closest('[data-comment]');
            if (card.querySelector('form.comments-inline')) return;
            const form = inlineForm(link.closest('[data-comments]'), 'edit', item.dataset.comment);
            if (!form) return;
            form.setAttribute('action', link.dataset.commentsEdit);
            form.dataset.commentsForm = 'update';
            form.querySelector('[name=parent_id]')?.remove();
            form.insertAdjacentHTML('afterbegin', '<input type="hidden" name="_method" value="PATCH">');
            const textarea = form.querySelector('textarea');
            textarea.value = card.dataset.commentsSource || '';
            textarea.placeholder = '';
            form.querySelector('button[type=submit]:not([data-comments-nojs])').textContent = 'Save changes';
            card.querySelector('.comments-body').hidden = true;
            card.querySelector('.comments-actions').hidden = true;
            card.querySelector('.comments-meta').after(form);
            textarea.focus();
            textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        };

        const closeInline = (form) => {
            const card = form.closest('[data-comments-card]');
            if (card) {
                card.querySelector('.comments-body')?.removeAttribute('hidden');
                card.querySelector('.comments-actions')?.removeAttribute('hidden');
            }
            const item = form.closest('[data-comment]');
            form.remove();
            item?.querySelector(':scope > [data-comments-card] a, :scope > [data-comments-card] button')?.focus();
        };

        document.addEventListener('click', (event) => {
            const target = event.target.closest('[data-comments-tab], [data-comments-reply], [data-comments-edit], [data-comments-cancel]');
            if (!target || !target.closest('[data-comments]')) {
                document.querySelectorAll('.comments-picker[open]').forEach((picker) => {
                    if (!picker.contains(event.target)) picker.open = false;
                });
                return;
            }
            event.preventDefault();
            if (target.dataset.commentsTab) showTab(target.closest('form'), target.dataset.commentsTab);
            else if (target.dataset.commentsReply) openReply(target);
            else if (target.dataset.commentsEdit) openEdit(target);
            else closeInline(target.closest('form'));
        });

        document.addEventListener('keydown', (event) => {
            if (!event.target.closest?.('[data-comments]')) return;
            if (event.key === 'Enter' && (event.metaKey || event.ctrlKey) && event.target.matches('textarea')) {
                event.preventDefault();
                event.target.form.requestSubmit();
            }
            if (event.key === 'Escape') {
                const picker = event.target.closest('.comments-picker[open]');
                if (picker) { picker.open = false; picker.querySelector('summary').focus(); }
                else if (event.target.closest('form.comments-inline')) closeInline(event.target.closest('form'));
            }
        });

        // --- Submitting -----------------------------------------------------
        const done = {
            create(form, data, root) {
                const node = fragment(data.html);
                if (node) {
                    node.classList.add('is-new');
                    enhance(node);
                    const parent = data.data?.parent_id ? root.querySelector('#comment-' + data.data.parent_id) : null;
                    if (parent) {
                        let replies = parent.querySelector(':scope > .comments-replies');
                        if (!replies) {
                            replies = document.createElement('ol');
                            replies.className = 'comments-replies';
                            parent.append(replies);
                        }
                        replies.append(node);
                    } else {
                        const list = root.querySelector('[data-comments-list]');
                        root.dataset.commentsSort === 'newest' ? list.prepend(node) : list.append(node);
                    }
                    node.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }
                if (form.classList.contains('comments-inline')) {
                    closeInline(form);
                } else {
                    form.reset();
                    if (form.querySelector('[data-comments-tab]')) showTab(form, 'write');
                }
                setCount(root, data.comments_count);
                flash(root, data.data && !data.data.approved ? data.message : '');
            },
            update(form, data) {
                const card = form.closest('[data-comments-card]');
                const node = fragment(data.html);
                if (card && node) { enhance(node); card.replaceWith(node); }
            },
            delete(form, data, root) {
                const item = form.closest('[data-comment]');
                if (item.querySelector(':scope > .comments-replies > li')) {
                    item.querySelector(':scope > [data-comments-card]').replaceWith(fragment(data.html));
                } else {
                    item.remove();
                }
                setCount(root, data.comments_count);
                flash(root, data.message);
            },
            react(form, data, root, submitter) {
                const node = fragment(data.html);
                if (!node) return;
                form.replaceWith(node);
                const emoji = submitter?.value;
                const chip = [...node.querySelectorAll('.comments-reaction')].find((button) => button.value === emoji);
                (chip || node.querySelector('summary'))?.focus();
            },
        };

        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('form[data-comments-form]');
            const root = form?.closest('[data-comments]');
            const kind = form?.dataset.commentsForm;
            if (!root || !done[kind]) return;
            const submitter = event.submitter;
            if (submitter?.dataset.commentsNojs !== undefined && submitter?.value === 'preview') return;

            event.preventDefault();
            if (kind === 'delete' && !confirm('Delete this comment? Replies to it stay.')) return;
            if (form.dataset.commentsBusy) return;

            const body = new FormData(form);
            if (submitter?.name) body.set(submitter.name, submitter.value);
            form.dataset.commentsBusy = '1';
            form.querySelectorAll('button[type=submit]').forEach((button) => { button.disabled = true; });
            setError(form, '');

            try {
                const response = await fetch(form.getAttribute('action'), { method: 'POST', headers: headers(form), body, credentials: 'same-origin' });
                const data = await response.json().catch(() => ({}));
                if (response.status === 202) { form.reset(); return; }
                if (!response.ok) { setError(form, errorFrom(response, data)); return; }
                done[kind](form, data, root, submitter);
            } catch {
                setError(form, 'Couldn’t reach the server. Check your connection and try again.');
            } finally {
                delete form.dataset.commentsBusy;
                form.querySelectorAll('button[type=submit]').forEach((button) => { button.disabled = false; });
            }
        });
    })();
</script>
