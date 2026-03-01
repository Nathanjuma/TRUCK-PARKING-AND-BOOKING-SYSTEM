// IndexedDB for offline storage
const DB_NAME = 'truckpark';
const DB_VERSION = 1;

function openDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);
    
    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);
    
    request.onupgradeneeded = (event) => {
      const db = event.target.result;
      
      // Create stores
      if (!db.objectStoreNames.contains('stations')) {
        db.createObjectStore('stations', { keyPath: 'id' });
      }
      if (!db.objectStoreNames.contains('bookings')) {
        db.createObjectStore('bookings', { keyPath: 'id' });
      }
      if (!db.objectStoreNames.contains('offlineBookings')) {
        db.createObjectStore('offlineBookings', { keyPath: 'id', autoIncrement: true });
      }
    };
  });
}

// Save data for offline
async function saveOfflineData(storeName, data) {
  const db = await openDB();
  const tx = db.transaction(storeName, 'readwrite');
  const store = tx.objectStore(storeName);
  
  if (Array.isArray(data)) {
    data.forEach(item => store.put(item));
  } else {
    store.put(data);
  }
  
  return tx.complete;
}

// Get offline data
async function getOfflineData(storeName) {
  const db = await openDB();
  const tx = db.transaction(storeName, 'readonly');
  const store = tx.objectStore(storeName);
  return store.getAll();
}

// Save booking for offline sync
async function saveOfflineBooking(bookingData) {
  const db = await openDB();
  const tx = db.transaction('offlineBookings', 'readwrite');
  const store = tx.objectStore('offlineBookings');
  
  await store.add({
    data: bookingData,
    timestamp: new Date().toISOString()
  });
  
  // Register for background sync
  if ('serviceWorker' in navigator && 'SyncManager' in window) {
    const registration = await navigator.serviceWorker.ready;
    await registration.sync.register('sync-bookings');
  }
  
  return tx.complete;
}