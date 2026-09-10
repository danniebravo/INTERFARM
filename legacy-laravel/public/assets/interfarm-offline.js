(() => {
  const DB_NAME = 'interfarm-offline';
  const DB_VERSION = 1;
  const STORE = 'queued-requests';
  const resolveRuntimeCache = async () => {
    try {
      const keys = await caches.keys();
      const match = keys.find((k) => /^interfarm-.*-runtime$/.test(k));
      if (match) return match;
    } catch (e) {}
    return 'interfarm-v26-runtime';
  };
  const SYNC_EVENT = 'interfarm-sync-queue';

  const offlinePages = [
    '/dashboard',
    '/animales',
    '/animales/crear',
    '/produccion',
    '/eventos',
    '/reportes',
    '/configuracion',
    '/settings',
    '/profile',
    '/mi-facturacion',
    '/facturacion/facturas',
    '/facturacion/pagos'
  ];

  const allowedPrefixes = [
    '/animales',
    '/produccion',
    '/eventos',
    '/reportes',
    '/fincas',
    '/configuracion',
    '/settings',
    '/profile',
    '/mi-facturacion',
    '/facturacion'
  ];

  const deniedPrefixes = [
    '/admin',
    '/logout',
    '/login',
    '/register',
    '/finanzas',
    '/lotes',
    '/notificaciones',
    '/facturacion/forma-de-pago',
    '/facturacion/checkout'
  ];

  const openDb = () => new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);

    request.onupgradeneeded = () => {
      const db = request.result;

      if (!db.objectStoreNames.contains(STORE)) {
        db.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
      }
    };

    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });

  const txStore = async (mode = 'readonly') => {
    const db = await openDb();
    return db.transaction(STORE, mode).objectStore(STORE);
  };

  const getAllQueued = async () => new Promise(async (resolve, reject) => {
    const store = await txStore();
    const request = store.getAll();

    request.onsuccess = () => resolve(request.result || []);
    request.onerror = () => reject(request.error);
  });

  const addQueued = async (payload) => new Promise(async (resolve, reject) => {
    const store = await txStore('readwrite');
    const request = store.add(payload);

    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });

  const deleteQueued = async (id) => new Promise(async (resolve, reject) => {
    const store = await txStore('readwrite');
    const request = store.delete(id);

    request.onsuccess = () => resolve();
    request.onerror = () => reject(request.error);
  });

  const countQueued = async () => (await getAllQueued()).length;

  const isAllowedAction = (url) => {
    if (url.origin !== window.location.origin) return false;
    if (deniedPrefixes.some((prefix) => url.pathname.startsWith(prefix))) return false;

    return allowedPrefixes.some((prefix) => url.pathname.startsWith(prefix));
  };

  const serializeForm = (form) => {
    const formData = new FormData(form);
    const fields = [];

    formData.forEach((value, key) => {
      if (value instanceof File) {
        if (!value.name || value.size === 0) {
          return;
        }

        fields.push({
          key,
          type: 'file',
          name: value.name,
          mime: value.type || 'application/octet-stream',
          lastModified: value.lastModified || Date.now(),
          blob: value
        });

        return;
      }

      fields.push({
        key,
        type: 'text',
        value
      });
    });

    return fields;
  };

  const showOfflineToast = (message) => {
    const existing = document.querySelector('.offline-sync-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'offline-sync-toast';
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => toast.remove(), 5200);
  };

  const cacheForOffline = async (url) => {
    if (!navigator.onLine || !('caches' in window)) {
      return false;
    }

    const target = new URL(url, window.location.origin);

    if (target.origin !== window.location.origin) {
      return false;
    }

    if (deniedPrefixes.some((prefix) => target.pathname.startsWith(prefix))) {
      return false;
    }

    try {
      const response = await fetch(target.toString(), {
        credentials: 'same-origin'
      });

      if (!response.ok) {
        return false;
      }

      const cache = await caches.open(await resolveRuntimeCache());
      await cache.put(target.toString(), response.clone());

      return true;
    } catch (error) {
      return false;
    }
  };

  const refreshDashboardCache = async () => {
    if (!navigator.onLine || !('caches' in window)) {
      return;
    }

    const dashboardUrls = [
      '/dashboard',
      `${window.location.origin}/dashboard`,
      '/',
      `${window.location.origin}/`
    ];

    try {
      const cacheNames = await caches.keys();

      await Promise.all(cacheNames.map(async (cacheName) => {
        const cache = await caches.open(cacheName);
        await Promise.all(dashboardUrls.map((url) => cache.delete(url)));
      }));

      await cacheForOffline('/dashboard');
    } catch (error) {
      console.error('No se pudo actualizar el panel en caché:', error);
    }
  };

  const warmOfflinePages = async () => {
    if (!navigator.onLine || !('caches' in window)) {
      return;
    }

    const linkedPages = Array.from(document.querySelectorAll('a[href]'))
      .map((link) => {
        try {
          return new URL(link.href, window.location.origin);
        } catch (error) {
          return null;
        }
      })
      .filter((url) => {
        if (!url || url.origin !== window.location.origin) return false;
        if (url.searchParams.has('page')) return false;
        if (deniedPrefixes.some((prefix) => url.pathname.startsWith(prefix))) return false;

        return allowedPrefixes.some((prefix) => url.pathname.startsWith(prefix))
          || url.pathname === '/dashboard'
          || url.pathname.startsWith('/facturacion');
      })
      .map((url) => url.pathname + url.search)
      .slice(0, 60);

    const uniquePages = [...new Set([...offlinePages, ...linkedPages])];

    for (const page of uniquePages) {
      await cacheForOffline(page);
    }
  };

  const updatePendingIndicator = async () => {
    const count = await countQueued();
    const existing = document.getElementById('offlineQueueBadge');

    if (!count) {
      existing?.remove();
      return;
    }

    let badge = existing;

    if (!badge) {
      badge = document.createElement('button');
      badge.id = 'offlineQueueBadge';
      badge.type = 'button';
      badge.className = 'offline-queue-badge';
      badge.addEventListener('click', () => replayQueue(true));
      document.body.appendChild(badge);
    }

    badge.textContent = `${count} registro${count === 1 ? '' : 's'} pendiente${count === 1 ? '' : 's'}`;
  };

  const queueForm = async (form) => {
    const action = new URL(form.action || window.location.href, window.location.href);
    const method = (form.method || 'POST').toUpperCase();
    const fields = serializeForm(form);

    await addQueued({
      url: action.toString(),
      method,
      fields,
      createdAt: new Date().toISOString(),
      pageTitle: document.title || 'InterFarm'
    });

    await updatePendingIndicator();
    showOfflineToast('Guardado localmente. Se enviará cuando vuelva internet.');
  };

  const replayQueue = async (manual = false) => {
    if (!navigator.onLine) {
      if (manual) showOfflineToast('Aún no hay conexión para sincronizar.');
      return;
    }

    const queued = await getAllQueued();

    if (!queued.length) {
      if (manual) showOfflineToast('No tienes registros pendientes.');
      return;
    }

    let synced = 0;
    let failed = 0;

    for (const item of queued) {
      const formData = new FormData();

      item.fields.forEach((field) => {
        if (Array.isArray(field)) {
          formData.append(field[0], field[1]);
          return;
        }

        if (field.type === 'file' && field.blob) {
          const file = new File([field.blob], field.name || 'archivo', {
            type: field.mime || 'application/octet-stream',
            lastModified: field.lastModified || Date.now()
          });

          formData.append(field.key, file, file.name);
          return;
        }

        formData.append(field.key, field.value ?? '');
      });

      try {
        const response = await fetch(item.url, {
          method: item.method,
          body: formData,
          credentials: 'same-origin',
          headers: {
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'X-Requested-With': 'XMLHttpRequest'
          }
        });

        if (response.ok || response.redirected) {
          await deleteQueued(item.id);
          synced += 1;
        } else {
          failed += 1;
        }
      } catch (error) {
        failed += 1;
      }
    }

    await updatePendingIndicator();

    if (synced > 0) {
      await refreshDashboardCache();
    }

    if (synced > 0 && failed === 0) {
      showOfflineToast(`${synced} registro${synced === 1 ? '' : 's'} sincronizado${synced === 1 ? '' : 's'}.`);
    } else if (synced > 0) {
      showOfflineToast(`${synced} sincronizado${synced === 1 ? '' : 's'}, ${failed} pendiente${failed === 1 ? '' : 's'} por revisar.`);
    } else if (manual) {
      showOfflineToast('No se pudieron sincronizar los registros pendientes.');
    }
  };

  const registerServiceWorker = () => {
    if (!('serviceWorker' in navigator)) {
      return;
    }

    navigator.serviceWorker.register('/service-worker.js')
      .then((registration) => registration.update())
      .catch((error) => console.error('No se pudo registrar la PWA:', error));
  };

  const registerSync = async () => {
    const registration = await navigator.serviceWorker?.ready;

    if (registration && 'sync' in registration) {
      try {
        await registration.sync.register(SYNC_EVENT);
        return;
      } catch (error) {
        void error;
      }
    }

    replayQueue();
  };

  document.addEventListener('submit', async (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) return;
    if (navigator.onLine) return;
    if ((form.method || 'GET').toUpperCase() === 'GET') return;
    if (form.dataset.offlineSync === 'false') return;

    const action = new URL(form.action || window.location.href, window.location.href);

    if (!isAllowedAction(action)) return;

    event.preventDefault();

    try {
      await queueForm(form);
      form.reset();
    } catch (error) {
      showOfflineToast('No se pudo guardar localmente. Intenta de nuevo.');
    }
  }, true);

  window.addEventListener('online', () => {
    showOfflineToast('Conexión recuperada. Sincronizando pendientes...');
    registerSync();
  });

  navigator.serviceWorker?.addEventListener('message', (event) => {
    if (event.data?.type === 'SYNC_OFFLINE_QUEUE') {
      replayQueue();
    }
  });

  document.addEventListener('DOMContentLoaded', () => {
    registerServiceWorker();
    updatePendingIndicator();

    if (navigator.onLine) {
      replayQueue();
      setTimeout(warmOfflinePages, 1000);
    }
  });
})();
