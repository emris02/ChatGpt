const CACHE_NAME = 'registre-employe-cache-v1';
const urlsToCache = [
  '/',
  '/index.php',
  '/styles.css',
  '/main.js',
  '/assets/xpertpro.png'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => response || fetch(event.request))
  );
});

// Notifications push
self.addEventListener('push', function(event) {
  const data = event.data ? event.data.json() : {};
  const title = data.title || 'Notification';
  const options = {
    body: data.body || '',
    icon: '/assets/xpertpro.png',
    badge: '/assets/xpertpro.png'
  };
  event.waitUntil(self.registration.showNotification(title, options));
}); 