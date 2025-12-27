(() => {
    'use strict';

    // Intercept and AJAX-submit any form marked with `data-ajax="1"`.
    // This is progressive enhancement: without JS, forms still submit normally and the page reloads.
    const ajaxFormSelector = 'form[data-ajax="1"]';
    const listRootSelector = '[data-list-id]';
    const listDetailsSelector = 'details.todo-list-details';

    // Show a UIkit toast if available; fall back to `alert()` otherwise.
    function notify(message, status = 'danger', timeoutMs = 4000) {
        if (window.UIkit && typeof window.UIkit.notification === 'function') {
            window.UIkit.notification({ message, status, timeout: timeoutMs });
            return;
        }
        window.alert(message);
    }

    // Parse a JSON response body safely; returns `null` on invalid JSON.
    function parseJsonResponse(text) {
        if (!text) {
            return null;
        }
        try {
            return JSON.parse(text);
        } catch {
            return null;
        }
    }

    function getCsrfToken() {
        return String(document.body?.dataset?.csrfToken ?? '');
    }

    let didWarnListStateSaveFailure = false;

    async function persistListExpandedState(listId, isExpanded) {
        const csrfToken = getCsrfToken();
        if (!csrfToken) {
            return;
        }

        const formData = new FormData();
        formData.set('action', 'set_list_expanded');
        formData.set('csrf_token', csrfToken);
        formData.set('list_id', String(listId));
        formData.set('is_expanded', isExpanded ? '1' : '0');

        try {
            const res = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
                credentials: 'same-origin',
            });

            const text = await res.text();
            const data = parseJsonResponse(text);
            if (!res.ok || !data || !data.ok) {
                if (!didWarnListStateSaveFailure) {
                    didWarnListStateSaveFailure = true;
                    notify(data?.error || 'Could not save list open/closed state.', 'warning');
                }
            }
        } catch {
            // Ignore (e.g. offline). State will still apply on this page load.
        }
    }

    function todoIdFromCheckboxId(id) {
        if (typeof id !== 'string' || !id.startsWith('todo-')) {
            return null;
        }
        const v = Number.parseInt(id.slice(5), 10);
        return Number.isFinite(v) ? v : null;
    }

    function getTodoIds(listRootEl) {
        if (!listRootEl) {
            return [];
        }
        const ids = [];
        const inputs = listRootEl.querySelectorAll('input[id^="todo-"]');
        for (const input of inputs) {
            const id = todoIdFromCheckboxId(input.id);
            if (id !== null) {
                ids.push(id);
            }
        }
        return ids;
    }

    function findTodoListItem(listRootEl, todoId) {
        if (!listRootEl || todoId === null || todoId === undefined || todoId === '') {
            return null;
        }
        const checkbox = listRootEl.querySelector(`#todo-${todoId}`);
        return checkbox ? checkbox.closest('li') : null;
    }

    // Keep the todo in the same visual position (avoid resorting),
    // while still reflecting any server-side inserts/deletes.
    function applyTodoUpdatePreservingOrder(currentList, replacementList, todoId) {
        const currentUl = currentList.querySelector('ul.uk-list');
        const replacementUl = replacementList.querySelector('ul.uk-list');
        if (!currentUl || !replacementUl) {
            return false;
        }

        const currentTodoLi = findTodoListItem(currentList, todoId);
        const replacementTodoLi = findTodoListItem(replacementList, todoId);
        if (!currentTodoLi || !replacementTodoLi) {
            return false;
        }

        const currentIds = getTodoIds(currentList);
        const replacementIds = getTodoIds(replacementList);
        const currentSet = new Set(currentIds);
        const replacementSet = new Set(replacementIds);

        const removedIds = currentIds.filter((id) => !replacementSet.has(id));
        const addedIds = replacementIds.filter((id) => !currentSet.has(id));

        // Replace only the toggled <li> so it doesn't move within the list.
        currentTodoLi.replaceWith(replacementTodoLi);

        // Remove todos that disappeared (e.g. recurrence undo removed the next occurrence).
        for (const id of removedIds) {
            const li = findTodoListItem(currentList, id);
            if (li) {
                li.remove();
            }
        }

        // Add any new todos created by the server (e.g. recurrence completion created the next occurrence).
        for (const id of addedIds) {
            const li = findTodoListItem(replacementList, id);
            if (li) {
                currentUl.appendChild(li);
            }
        }

        const currentCountEl = currentList.querySelector('.todo-list-task-count');
        const replacementCountEl = replacementList.querySelector('.todo-list-task-count');
        if (currentCountEl && replacementCountEl) {
            currentCountEl.textContent = replacementCountEl.textContent;
        }

        return true;
    }

    // Submit a form via `fetch()` and replace the affected list card with server-rendered HTML.
    async function submitAjaxForm(form) {
        // Prevent double-submits (e.g. fast clicks).
        if (form.dataset.submitting === '1') {
            return;
        }
        form.dataset.submitting = '1';

        // Important: use the *attribute* value, not `form.action`.
        // If the form contains an input named "action", some browsers expose it as `form.action`,
        // which breaks URL resolution (you'll see "[object HTMLInputElement]" requests).
        const actionUrl = new URL(form.getAttribute('action') || window.location.href, window.location.href);

        // Collect the form fields as multipart/form-data (works with inputs, checkboxes, etc).
        const formData = new FormData(form);
        const action = formData.get('action');
        const listId = formData.get('list_id');
        const todoId = formData.get('id');

        // Mark the list card as busy for assistive tech (and potential styling).
        const listEl = form.closest('[data-list-id]');
        const busyEl = listEl || (action === 'add' && listId ? document.querySelector(`[data-list-id="${listId}"]`) : null);
        if (busyEl) {
            busyEl.setAttribute('aria-busy', 'true');
        }

        try {
            // Ask the server for JSON (IndexController will detect this and respond with JSON).
            const res = await fetch(actionUrl.toString(), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
                credentials: 'same-origin',
            });

            // We read the body as text first so we can display a friendly error even if JSON is invalid.
            const text = await res.text();
            const data = parseJsonResponse(text);

            if (!data) {
                notify(`Unexpected response (HTTP ${res.status}).`, 'danger');
                return;
            }

            // If the server tells us to redirect (e.g. session expired), do it.
            if (data.redirect) {
                window.location.href = data.redirect;
                return;
            }

            // Any error -> show a notification.
            if (!res.ok || !data.ok) {
                notify(data.error || `Request failed (HTTP ${res.status}).`, 'danger');
                return;
            }

            // Successful responses must include a list id + HTML for the updated list card.
            if (!data.list_id || !data.html) {
                notify('Server response missing updated HTML.', 'danger');
                return;
            }

            // Replace the list card in-place with the freshly rendered HTML.
            const current = document.querySelector(`[data-list-id="${data.list_id}"]`);
            const currentDetailsOpen = current?.querySelector(listDetailsSelector)?.open;
            const template = document.createElement('template');
            template.innerHTML = String(data.html).trim();
            const replacement = template.content.firstElementChild;

            if (!replacement) {
                notify('Server response returned empty HTML.', 'danger');
                return;
            }

            let didReplace = false;
            if ((action === 'toggle' || action === 'update_due_date') && todoId && current) {
                didReplace = applyTodoUpdatePreservingOrder(current, replacement, todoId);
            }

            if (!didReplace) {
                if (current) {
                    current.replaceWith(replacement);

                    const replacementDetails = replacement.querySelector(listDetailsSelector);
                    if (replacementDetails && typeof currentDetailsOpen === 'boolean' && replacementDetails.open !== currentDetailsOpen) {
                        replacementDetails.open = currentDetailsOpen;
                    }
                } else {
                    // If the list wasn't found in the DOM (unexpected), append it.
                    const container = document.getElementById('lists');
                    if (container) {
                        container.appendChild(replacement);
                    }
                }
            }

            // Let UIkit re-scan the updated DOM (grid/layout, icons, etc).
            if (window.UIkit && typeof window.UIkit.update === 'function') {
                const container = document.getElementById('lists');
                window.UIkit.update(container || replacement);
            }

            // Small UX touch: restore focus after an action so keyboard users aren't "lost".
            if (action === 'add') {
                notify('Task added.', 'success', 2000);
                const title = form.querySelector('input[name="title"]');
                if (title) {
                    title.value = '';
                    title.focus();
                }
            } else if (action === 'toggle' && todoId) {
                const checkbox = document.getElementById(`todo-${todoId}`);
                if (checkbox) {
                    checkbox.focus();
                }
            } else if (action === 'update_due_date' && todoId) {
                const input = document.getElementById(`todo-due-${todoId}`);
                if (input) {
                    input.focus();
                }
            }
        } catch (err) {
            // Network / fetch failure (timeout, offline, server down, etc).
            notify('Network error while saving. Please try again.', 'danger');
        } finally {
            if (busyEl) {
                busyEl.removeAttribute('aria-busy');
            }
            delete form.dataset.submitting;
        }
    }

    // Single delegated event listener, so it works for dynamically replaced content too.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        if (!form.matches(ajaxFormSelector)) {
            return;
        }

        // Prevent normal form submission (full page reload) and do the AJAX path instead.
        event.preventDefault();
        submitAjaxForm(form);
    });

    document.addEventListener(
        'toggle',
        (event) => {
            const target = event.target;
            if (!(target instanceof HTMLDetailsElement)) {
                return;
            }
            if (!target.matches(listDetailsSelector)) {
                return;
            }

            const listEl = target.closest(listRootSelector);
            const listId = listEl?.dataset?.listId ? String(listEl.dataset.listId) : null;
            if (!listId) {
                return;
            }

            persistListExpandedState(listId, target.open);
        },
        true
    );
})();
