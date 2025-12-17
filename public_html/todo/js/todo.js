(() => {
    'use strict';

    // Intercept and AJAX-submit any form marked with `data-ajax="1"`.
    // This is progressive enhancement: without JS, forms still submit normally and the page reloads.
    const ajaxFormSelector = 'form[data-ajax="1"]';

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
        const todoId = action === 'toggle' ? formData.get('id') : null;

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
            const template = document.createElement('template');
            template.innerHTML = String(data.html).trim();
            const replacement = template.content.firstElementChild;

            if (!replacement) {
                notify('Server response returned empty HTML.', 'danger');
                return;
            }

            if (current) {
                current.replaceWith(replacement);
            } else {
                // If the list wasn't found in the DOM (unexpected), append it.
                const container = document.getElementById('lists');
                if (container) {
                    container.appendChild(replacement);
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
})();
