// ============================================
// Mock API transport.
// Intercepts any fetch() to /api/*.php and answers it from localStorage
// instead of the network. This is what lets the page scripts
// (sales.js, stock.js, …) run unmodified.
// ============================================

(() => {

    const realFetch = window.fetch ? window.fetch.bind(window) : null;

    // Small delay so loading spinners in the UI actually render.
    const LATENCY_MS = 110;

    function parseParams(url, options) {
        const params = {};

        const qIndex = url.indexOf('?');
        if (qIndex !== -1) {
            new URLSearchParams(url.slice(qIndex + 1)).forEach((v, k) => { params[k] = v; });
        }

        const body = options && options.body;
        if (!body) return params;

        if (typeof body === 'string') {
            new URLSearchParams(body).forEach((v, k) => { params[k] = v; });
        } else if (body instanceof URLSearchParams) {
            body.forEach((v, k) => { params[k] = v; });
        } else if (typeof FormData !== 'undefined' && body instanceof FormData) {
            body.forEach((v, k) => { params[k] = v; });
        }

        return params;
    }

    function endpointOf(url) {
        const after = url.split('/api/')[1] || '';
        return after.split('?')[0].split('#')[0];
    }

    function respond(payload) {
        const text = JSON.stringify(payload);
        return {
            ok: true, status: 200, statusText: 'OK',
            headers: new Headers({ 'Content-Type': 'application/json' }),
            url: '',
            json: () => Promise.resolve(JSON.parse(text)),
            text: () => Promise.resolve(text),
            clone() { return respond(payload); },
        };
    }

    window.fetch = function (input, options) {
        const url = (typeof input === 'string') ? input : (input && input.url) || '';

        if (!url.includes('/api/')) {
            if (realFetch) return realFetch(input, options);
            return Promise.reject(new Error('fetch unavailable'));
        }

        const endpoint = endpointOf(url);
        const params   = parseParams(url, options);

        return new Promise(resolve => {
            setTimeout(() => {
                resolve(respond(ApiRouter.handle(endpoint, params)));
            }, LATENCY_MS);
        });
    };
})();
