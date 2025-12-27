(() => {
    'use strict';

    const ajaxFormSelector = 'form[data-ajax="1"]';

    function notify(message, status = 'danger', timeoutMs = 4000) {
        if (window.UIkit && typeof window.UIkit.notification === 'function') {
            window.UIkit.notification({ message, status, timeout: timeoutMs });
            return;
        }
        window.alert(message);
    }

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

    async function submitAjaxForm(form, formData) {
        if (form.dataset.submitting === '1') {
            return;
        }
        form.dataset.submitting = '1';

        const actionUrl = new URL(form.getAttribute('action') || window.location.href, window.location.href);
        const listOrderSection = document.getElementById('list-order');
        if (listOrderSection) {
            listOrderSection.setAttribute('aria-busy', 'true');
        }

        try {
            const res = await fetch(actionUrl.toString(), {
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
            if (!data) {
                notify(`Unexpected response (HTTP ${res.status}).`, 'danger');
                return;
            }

            if (!res.ok || !data.ok) {
                notify(data.error || `Request failed (HTTP ${res.status}).`, 'danger');
                return;
            }

            if (!data.html) {
                notify('Server response missing updated HTML.', 'danger');
                return;
            }

            const template = document.createElement('template');
            template.innerHTML = String(data.html).trim();
            const replacement = template.content.firstElementChild;
            const current = document.getElementById('list-order');

            if (!replacement) {
                notify('Server response returned empty HTML.', 'danger');
                return;
            }

            if (current) {
                current.replaceWith(replacement);
            }

            if (window.UIkit && typeof window.UIkit.update === 'function') {
                window.UIkit.update(replacement);
            }

            if (data.msg) {
                notify(String(data.msg), 'success', 2000);
            }
        } catch {
            notify('Network error while saving. Please try again.', 'danger');
        } finally {
            if (listOrderSection) {
                listOrderSection.removeAttribute('aria-busy');
            }
            delete form.dataset.submitting;
        }
    }

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        if (!form.matches(ajaxFormSelector)) {
            return;
        }

        const formData = new FormData(form);
        const action = formData.get('action');
        if (action !== 'reorder_list') {
            return;
        }

        event.preventDefault();
        submitAjaxForm(form, formData);
    });
})();

