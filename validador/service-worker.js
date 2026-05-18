const CACHE_NAME = 'cine-soe-validador-v11-cleancontrols';
const OFFLINE_URL = '../offline.html';
const STATIC_ASSETS = [
  'escaner.php',
  '../offline.html',
  '../assets/css/bootstrap.min.css',
  '../assets/css/escaner.css',
  '../assets/js/html5-qrcode.min.js',
  '../assets/js/escaner.js',
  '../assets/icons/icon-192.png',
  '../assets/icons/icon-512.png'
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key)))).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  if (url.pathname.includes('/api/validar-qr.php')) {
    event.respondWith(fetch(event.request));
    return;
  }

  if (
    url.pathname.endsWith('/validador/escaner.php') ||
    url.pathname.endsWith('/assets/js/escaner.js') ||
    url.pathname.endsWith('/assets/css/escaner.css') ||
    url.search.includes('20260517-camera') ||
    url.search.includes('20260517-flow') ||
    url.search.includes('20260517-flow3s') ||
    url.search.includes('20260517-moviecounter') ||
    url.search.includes('20260517-movietitle') ||
    url.search.includes('20260517-counterlabel') ||
    url.search.includes('20260517-manualticket') ||
    url.search.includes('20260517-manualok') ||
    url.search.includes('20260517-manualvalid') ||
    url.search.includes('20260517-cleancontrols')
  ) {
    event.respondWith(
      fetch(event.request).then(response => {
        const copy = response.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(event.request, copy));
        return response;
      }).catch(() => caches.match(event.request))
    );
    return;
  }

  if (url.pathname.includes('/api/')) {
    event.respondWith(
      fetch(event.request).then(response => {
        const copy = response.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(event.request, copy));
        return response;
      }).catch(() => caches.match(event.request))
    );
    return;
  }

  event.respondWith(
    caches.match(event.request).then(cached => cached || fetch(event.request).then(response => {
      const copy = response.clone();
      caches.open(CACHE_NAME).then(cache => cache.put(event.request, copy));
      return response;
    })).catch(() => caches.match(OFFLINE_URL))
  );
});
