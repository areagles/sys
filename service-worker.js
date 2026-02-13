// service-worker.js - (Royal ERP V20.0 - Smart Push Engine)
const CACHE_NAME = 'royal-erp-v20-core';
const urlsToCache = [
    './',
    'manifest.json',
    'assets/img/icon-192x192.png',
    'assets/img/icon-512x512.png'
];

self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(urlsToCache)));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    // استثناء التحديثات الحية من الكاش لضمان دقة البيانات
    if (event.request.url.includes('live_updates=1')) return;
    
    event.respondWith(
        fetch(event.request).catch(() => caches.match(event.request))
    );
});

// معالج النقر على الإشعارات (يفتح التطبيق على العملية المحددة)
self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    const jobId = event.notification.data.job_id;
    const targetUrl = jobId ? `job_details.php?id=${jobId}` : 'dashboard.php';

    event.waitUntil(
        clients.matchAll({type: 'window', includeUncontrolled: true}).then(function(clientList) {
            // إذا كانت الصفحة مفتوحة، قم بالتركيز عليها
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url.includes('dashboard.php') || client.url.includes('job_details.php')) {
                    return client.focus().then(c => c.navigate(targetUrl));
                }
            }
            // إذا كانت مغلقة، افتح نافذة جديدة
            if (clients.openWindow) return clients.openWindow(targetUrl);
        })
    );
});