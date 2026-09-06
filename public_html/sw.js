self.addEventListener('push', function (event) {
    const data = event.data ? event.data.json() : {};
    const title = data.title || 'Erinnerung';
    const options = {
        body:  data.body  || '',
        icon:  data.icon  || 'https://reminder.schnueddels.de/assets/icon-192.png',
        badge: data.badge || 'https://reminder.schnueddels.de/assets/icon-192.png',
        data:  { url: data.url || 'https://reminder.schnueddels.de/' },
    };
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : 'https://reminder.schnueddels.de/';
    event.waitUntil(clients.openWindow(url));
});
