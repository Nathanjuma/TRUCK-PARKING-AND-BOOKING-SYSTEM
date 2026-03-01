const CACHE_NAME = 'truckpark-v1';
const urlsToCache = [
  '/truckpark/',
  '/truckpark/index.html',
  '/truckpark/manifest.json',
  '/truckpark/users.php',
  '/truckpark/stations.php',
  '/truckpark/bookings.php',
  '/truckpark/reviews.php',
  'https://cdn.tailwindcss.com/3.4.17',
  'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap'
];

// Install Service Worker
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
      })
  );
});

// Activate Service Worker
self.addEventListener('activate', event => {
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

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // Cache hit - return response
        if (response) {
          return response;
        }

        // Clone the request
        const fetchRequest = event.request.clone();

        return fetch(fetchRequest).then(response => {
          // Check if we received a valid response
          if (!response || response.status !== 200 || response.type !== 'basic') {
            return response;
          }

          // Clone the response
          const responseToCache = response.clone();

          caches.open(CACHE_NAME)
            .then(cache => {
              // Don't cache API responses with errors
              if (event.request.url.includes('.php') && !response.ok) {
                return;
              }
              cache.put(event.request, responseToCache);
            });

          return response;
        });
      })
  );
});

// Background sync for offline actions
self.addEventListener('sync', event => {
  if (event.tag === 'sync-bookings') {
    event.waitUntil(syncBookings());
  }
});

async function syncBookings() {
  try {
    const db = await openDB();
    const offlineBookings = await getOfflineBookings(db);
    
    for (const booking of offlineBookings) {
      try {
        const response = await fetch('bookings.php?action=create', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(booking.data)
        });
        
        if (response.ok) {
          await removeOfflineBooking(db, booking.id);
        }
      } catch (error) {
        console.error('Failed to sync booking:', error);
      }
    }
  } catch (error) {
    console.error('Sync failed:', error);
  }
}