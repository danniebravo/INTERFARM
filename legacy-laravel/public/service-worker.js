const CACHE_VERSION = 'interfarm-v26';
const APP_SHELL_CACHE = `${CACHE_VERSION}-shell`;
const RUNTIME_CACHE = `${CACHE_VERSION}-runtime`;

const APP_SHELL = [
  '/offline.html',
  '/manifest.webmanifest',
  '/manifest.webmanifest?v=4',
  '/images/logo.png',
  '/favicon.ico',
  '/favicon.ico?v=3',
  '/images/favicon-16x16.png',
  '/images/favicon-16x16.png?v=3',
  '/images/favicon-32x32.png',
  '/images/favicon-32x32.png?v=3',
  '/images/apple-touch-icon.png',
  '/images/apple-touch-icon.png?v=3',
  '/images/pwa-icon-192.png',
  '/images/pwa-icon-192.png?v=3',
  '/images/pwa-icon-512.png',
  '/images/pwa-icon-512.png?v=3',
  '/assets/interfarm-offline.js',
  '/assets/interfarm-offline.js?v=22'
];

const EXTERNAL_APP_SHELL = [
  '/vendor/tailwind.js',
  'https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js',
  'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css',
  'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js',
  'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css',
  'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js',
  'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales-all.global.min.js',
  'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
  'https://cdn.jsdelivr.net/npm/flatpickr',
  'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js',
  'https://cdn.jsdelivr.net/npm/heic2any/dist/heic2any.min.js'
];

const NAVIGATION_CACHE_DENYLIST = [
  '/admin',
  '/login',
  '/register',
  '/logout',
  '/finanzas',
  '/lotes',
  '/facturacion/forma-de-pago'
];

const OPERATIONAL_FALLBACKS = [
  ['/animales/crear', '/animales/crear'],
  ['/animales', '/animales'],
  ['/produccion', '/produccion'],
  ['/eventos', '/eventos'],
  ['/reportes', '/reportes'],
  ['/configuracion', '/configuracion'],
  ['/settings', '/settings'],
  ['/profile', '/profile'],
  ['/mi-facturacion', '/mi-facturacion'],
  ['/facturacion/facturas', '/facturacion/facturas'],
  ['/facturacion/pagos', '/facturacion/pagos'],
  ['/dashboard', '/dashboard'],
  ['/', '/dashboard']
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(APP_SHELL_CACHE)
      .then((cache) => Promise.allSettled(
        APP_SHELL.map((url) => cache.add(new Request(url, { cache: 'reload' })).catch(() => null))
      ))
      .then(() => cacheExternalShell())
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys
          .filter((key) => ![APP_SHELL_CACHE, RUNTIME_CACHE].includes(key))
          .map((key) => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  if (request.method !== 'GET') {
    return;
  }

  if (/(^|\.)googleapis\.com$/.test(url.hostname) || /(^|\.)gstatic\.com$/.test(url.hostname)) {
    return;
  }

  if (url.origin !== self.location.origin) {
    event.respondWith(cacheFirst(request));
    return;
  }

  if (url.pathname.startsWith('/lotes/buscar-ubicacion')) {
    event.respondWith(fetch(request));
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(networkFirstNavigation(request));
    return;
  }

  event.respondWith(staleWhileRevalidate(request));
});

self.addEventListener('sync', (event) => {
  if (event.tag !== 'interfarm-sync-queue') {
    return;
  }

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then((clients) => {
        clients.forEach((client) => {
          client.postMessage({ type: 'SYNC_OFFLINE_QUEUE' });
        });
      })
  );
});

self.addEventListener('push', (event) => {
  let payload = {};

  try {
    payload = event.data ? event.data.json() : {};
  } catch (error) {
    payload = {
      title: 'InterFarm',
      body: event.data ? event.data.text() : 'Tienes nuevas notificaciones de tu finca.'
    };
  }

  const title = payload.title || 'InterFarm';
  const options = {
    body: payload.body || 'Tienes nuevas notificaciones de tu finca.',
    icon: payload.icon || '/images/pwa-icon-192.png?v=3',
    badge: payload.badge || '/images/pwa-icon-192.png?v=3',
    tag: payload.tag || 'interfarm-notifications',
    renotify: true,
    data: {
      url: payload.url || '/dashboard',
      ...(payload.data || {})
    }
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const targetUrl = event.notification.data?.url || '/dashboard';

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then((clients) => {
        for (const client of clients) {
          if ('focus' in client) {
            client.navigate(targetUrl);
            return client.focus();
          }
        }

        if (self.clients.openWindow) {
          return self.clients.openWindow(targetUrl);
        }

        return null;
      })
  );
});

async function networkFirstNavigation(request) {
  const cache = await caches.open(RUNTIME_CACHE);
  const url = new URL(request.url);
  const canCache = !NAVIGATION_CACHE_DENYLIST.some((prefix) => url.pathname.startsWith(prefix));

  try {
    const response = await fetch(request);

    if (canCache && response && response.ok) {
      cache.put(request, response.clone());
    }

    return response;
  } catch (error) {
    const cached = await cache.match(request);
    const moduleFallback = await matchOperationalFallback(url.pathname);
    const fallback = await caches.match('/offline.html');

    return cached || moduleFallback || fallback;
  }
}

async function matchOperationalFallback(pathname) {
  for (const [prefix, fallbackPath] of OPERATIONAL_FALLBACKS) {
    if (!pathname.startsWith(prefix)) {
      continue;
    }

    const runtimeMatch = await caches.match(fallbackPath);

    if (runtimeMatch) {
      return runtimeMatch;
    }
  }

  return null;
}

async function staleWhileRevalidate(request) {
  const cache = await caches.open(RUNTIME_CACHE);
  const cached = await cache.match(request);

  const network = fetch(request)
    .then((response) => {
      if (response && response.ok) {
        cache.put(request, response.clone());
      }

      return response;
    })
    .catch(() => cached);

  return cached || network;
}

async function cacheFirst(request) {
  const cached = await caches.match(request);

  if (cached) {
    return cached;
  }

  const cache = await caches.open(RUNTIME_CACHE);

  try {
    const response = await fetch(request);

    if (response && (response.ok || response.type === 'opaque')) {
      cache.put(request, response.clone());
    }

    return response;
  } catch (error) {
    return new Response('', { status: 504, statusText: 'Offline' });
  }
}

async function cacheExternalShell() {
  const cache = await caches.open(APP_SHELL_CACHE);

  await Promise.allSettled(
    EXTERNAL_APP_SHELL.map(async (url) => {
      const request = new Request(url, {
        mode: 'no-cors',
        credentials: 'omit'
      });

      const response = await fetch(request);

      if (response && (response.ok || response.type === 'opaque')) {
        await cache.put(request, response);
      }
    })
  );
}
