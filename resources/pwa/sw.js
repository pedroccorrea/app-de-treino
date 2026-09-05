const CACHE_VERSION = 'v2';
const APP_SHELL_CACHE = `setwave-shell-${CACHE_VERSION}`;
const STATIC_ASSETS_CACHE = `setwave-assets-${CACHE_VERSION}`;
const INERTIA_CACHE = `setwave-inertia-${CACHE_VERSION}`;
const PAGE_CACHE = `setwave-pages-${CACHE_VERSION}`;

const CURRENT_CACHES = [APP_SHELL_CACHE, STATIC_ASSETS_CACHE, INERTIA_CACHE, PAGE_CACHE];

const APP_SHELL_ASSETS = [
    '/manifest.json',
    '/favicon.ico',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
];

// Servido apenas quando o navegador pede uma navegação de documento completo
// (ex: F5) sem internet e nenhuma página ainda foi cacheada — evita a tela
// de erro nativa "sem conexão" do navegador na pior das hipóteses.
const OFFLINE_FALLBACK_HTML = `<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <title>Setwave — Offline</title>
    </head>
    <body style="font-family: sans-serif; text-align: center; padding: 2rem;">
        <h1>Você está offline</h1>
        <p>Conecte-se à internet para carregar esta página pela primeira vez.</p>
    </body>
</html>`;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(APP_SHELL_CACHE)
            .then((cache) => cache.addAll(APP_SHELL_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((cacheNames) => Promise.all(
                cacheNames
                    .filter((cacheName) => !CURRENT_CACHES.includes(cacheName))
                    .map((cacheName) => caches.delete(cacheName))
            ))
            .then(() => self.clients.claim())
    );
});

// Network-first: tenta a rede e atualiza o cache a cada sucesso; se a rede
// falhar (ou já sabemos, via navigator.onLine, que estamos offline), cai
// direto para a última resposta em cache daquela mesma requisição.
async function networkFirst(request, cacheName) {
    const cache = await caches.open(cacheName);

    if (!self.navigator.onLine) {
        const cachedResponse = await cache.match(request);
        if (cachedResponse) return cachedResponse;
    }

    try {
        const networkResponse = await fetch(request);
        cache.put(request, networkResponse.clone());
        return networkResponse;
    } catch (error) {
        // Offline ou timeout: cai para a última resposta conhecida daquela
        // requisição. Primeiro no cache específico da estratégia e, na falta
        // dele, em qualquer cache do Service Worker (`caches.match` global).
        const cachedResponse = await cache.match(request);
        if (cachedResponse) return cachedResponse;

        const fallbackResponse = await caches.match(request);
        if (fallbackResponse) return fallbackResponse;

        throw error;
    }
}

// Cache-first: usada para os pacotes compilados pelo Vite (`/build/assets/*`),
// cujo nome de arquivo já contém um hash do conteúdo — uma vez em cache, o
// arquivo nunca muda e não precisa ser revalidado contra a rede.
async function cacheFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cachedResponse = await cache.match(request);
    if (cachedResponse) return cachedResponse;

    const networkResponse = await fetch(request);
    cache.put(request, networkResponse.clone());
    return networkResponse;
}

// Navegação de documento completo (F5, digitar a URL, abrir um link externo):
// network-first, com fallback para a última página cacheada — na ausência de
// uma cópia daquela URL exata, usa qualquer página conhecida como app shell,
// já que o cliente Inertia consegue rehidratar a partir dela.
async function handleNavigationRequest(request) {
    try {
        return await networkFirst(request, PAGE_CACHE);
    } catch (error) {
        const cache = await caches.open(PAGE_CACHE);

        const cachedDashboard = await cache.match('/dashboard');
        if (cachedDashboard) return cachedDashboard;

        const cachedKeys = await cache.keys();
        if (cachedKeys.length > 0) return cache.match(cachedKeys[0]);

        return new Response(OFFLINE_FALLBACK_HTML, {
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
        });
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') {
        return;
    }

    const requestUrl = new URL(request.url);
    if (requestUrl.origin !== self.location.origin) {
        return;
    }

    // Pacotes compilados do Vite: cache-first e permanente.
    if (requestUrl.pathname.startsWith('/build/assets/')) {
        event.respondWith(cacheFirst(request, STATIC_ASSETS_CACHE));
        return;
    }

    // Requisições XHR do Inertia (navegação entre páginas do SPA sem reload)
    // ou qualquer outra chamada GET que espera JSON como resposta:
    // network-first, com fallback para o JSON da última visita bem-sucedida
    // àquela rota — é o que permite navegar pelos menus (/dashboard,
    // /workouts, /programs, /workouts/{id}, ...) sem erro de conexão.
    const isInertiaRequest = request.headers.get('X-Inertia') === 'true';
    const isJsonRequest = (request.headers.get('Accept') ?? '').includes('application/json');

    if (isInertiaRequest || isJsonRequest) {
        event.respondWith(networkFirst(request, INERTIA_CACHE));
        return;
    }

    // Navegação de documento completo.
    if (request.mode === 'navigate') {
        event.respondWith(handleNavigationRequest(request));
        return;
    }

    // Demais assets do app shell (manifest, ícones).
    if (APP_SHELL_ASSETS.includes(requestUrl.pathname)) {
        event.respondWith(cacheFirst(request, APP_SHELL_CACHE));
    }
});
