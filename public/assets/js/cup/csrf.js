/*
 * Every change in the panel carries the token of the signed in account:
 * the forms get a hidden field, the scripts (fetch, jQuery) a header.
 * The token is in <meta name="csrf-token">
 */
(() => {
    const token = (document.querySelector('meta[name="csrf-token"]') || {}).content;

    if (!token) {
        return;
    }

    const safe = (method) => ['GET', 'HEAD', 'OPTIONS'].includes(String(method || 'GET').toUpperCase());
    const local = (url) => {
        try {
            return new URL(url, location.href).origin === location.origin;
        } catch (e) {
            return false;
        }
    };

    // fetch()
    const originalFetch = window.fetch;
    window.fetch = (input, init = {}) => {
        const url = typeof input === 'string' ? input : input.url;
        const method = init.method || (typeof input !== 'string' && input.method) || 'GET';

        if (!safe(method) && local(url)) {
            const headers = new Headers(init.headers || (typeof input !== 'string' ? input.headers : undefined));
            headers.set('X-CSRF-Token', token);
            init = {...init, headers};
        }

        return originalFetch(input, init);
    };

    // jQuery ajax and $.post
    if (window.jQuery) {
        window.jQuery.ajaxPrefilter((options, original, xhr) => {
            if (!safe(options.type) && local(options.url)) {
                xhr.setRequestHeader('X-CSRF-Token', token);
            }
        });
    }

    // forms: now, and at the moment of sending for those made later
    const sign = (form) => {
        if (!form || String(form.method).toLowerCase() !== 'post' || !local(form.action) || form.querySelector('input[name="_csrf"]')) {
            return;
        }

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = '_csrf';
        input.value = token;
        form.appendChild(input);
    };

    document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('form').forEach(sign));
    document.addEventListener('submit', (e) => sign(e.target), true);
})();
