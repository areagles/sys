// portal/sw.js
// قمنا بتغيير الاسم هنا لـ v2 ليقوم المتصفح بتحميل النسخة الجديدة
const CACHE_NAME = 'arab-eagles-v2'; 
const urlsToCache = [
  'dashboard.html',
  'orders.html',
  'quotes.html',
  'invoices.html',
  'profile.html',
  'assets/images/logo.webp'
];

self.addEventListener('install', event => {
  // تخطي الانتظار وتفعيل الخدمة فوراً
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
  );
});

self.addEventListener('activate', event => {
  // كود لتنظيف الكاش القديم (v1) عند تفعيل v2
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    fetch(event.request).catch(() => caches.match(event.request))
  );
});