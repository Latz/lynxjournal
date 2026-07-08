import { setupServer } from 'msw/node';
import { http, HttpResponse } from 'msw';

const BASE    = 'https://example.com/wp-json/lynxjournal/v1';
const WP_JSON = 'https://example.com/wp-json/';

export const handlers = [
    http.get(`${BASE}/categories`, () =>
        HttpResponse.json([{ id: 1, name: 'Tech' }])
    ),
    http.post(`${BASE}/add-link`, () =>
        HttpResponse.json({ id: 1 }, { status: 200 })
    ),
    http.get(WP_JSON, () =>
        HttpResponse.json({ namespaces: ['lynxjournal/v1'] })
    ),
    // Nonce fetch is expected to fail in tests — keeps checkWpLogin tests honest
    http.get(`${BASE}/nonce`, () => HttpResponse.error()),
];

export const server = setupServer(...handlers);
