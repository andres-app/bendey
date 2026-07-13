/* =========================================================
   SERVICE WORKER
   Cambiar VERSION cada vez que publiques cambios importantes
========================================================= */

const VERSION = "6.1.0";

const STATIC_CACHE = `tiquepos-static-${VERSION}`;
const RUNTIME_CACHE = `tiquepos-runtime-${VERSION}`;

/*
 * Coloca solamente archivos que existan realmente.
 * Si uno no existe, la instalación del Service Worker puede fallar.
 */
const APP_SHELL = [
  "./",
  "./index.php",
  "./manifest.json",

  // Reemplaza por las rutas reales de tus iconos
  "./assets/icons/icon-192x192.png",
  "./assets/icons/icon-512x512.png"
];

/* =========================================================
   INSTALACIÓN
========================================================= */

self.addEventListener("install", event => {
  event.waitUntil(
    caches
      .open(STATIC_CACHE)
      .then(cache => cache.addAll(APP_SHELL))
      .then(() => self.skipWaiting())
  );
});

/* =========================================================
   ACTIVACIÓN Y LIMPIEZA DE CACHÉS ANTIGUAS
========================================================= */

self.addEventListener("activate", event => {
  event.waitUntil(
    caches
      .keys()
      .then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            const esCacheActual =
              cacheName === STATIC_CACHE ||
              cacheName === RUNTIME_CACHE;

            if (!esCacheActual) {
              console.log("Eliminando caché antigua:", cacheName);
              return caches.delete(cacheName);
            }

            return null;
          })
        );
      })
      .then(() => self.clients.claim())
  );
});

/* =========================================================
   FETCH
========================================================= */

self.addEventListener("fetch", event => {
  const request = event.request;

  // Solo manejar solicitudes GET
  if (request.method !== "GET") {
    return;
  }

  const url = new URL(request.url);

  // No interceptar recursos externos
  if (url.origin !== self.location.origin) {
    return;
  }

  /*
   * No guardar en caché:
   * - páginas PHP
   * - controladores
   * - peticiones dinámicas
   * - navegación del sistema
   */
  const esContenidoDinamico =
    request.mode === "navigate" ||
    url.pathname.endsWith(".php") ||
    url.pathname.includes("/Controllers/") ||
    url.pathname.includes("/Models/") ||
    url.searchParams.has("op");

  if (esContenidoDinamico) {
    event.respondWith(
      fetch(request, {
        cache: "no-store"
      }).catch(() => {
        return new Response(
          "No se pudo conectar con el servidor. Verifique su conexión.",
          {
            status: 503,
            headers: {
              "Content-Type": "text/plain; charset=UTF-8"
            }
          }
        );
      })
    );

    return;
  }

  /*
   * JavaScript y CSS:
   * NETWORK FIRST
   *
   * Primero busca la versión nueva en el servidor.
   * Si no hay internet, utiliza la última versión guardada.
   */
  if (
    request.destination === "script" ||
    request.destination === "style"
  ) {
    event.respondWith(networkFirst(request));
    return;
  }

  /*
   * Imágenes y fuentes:
   * CACHE FIRST
   *
   * Estos archivos normalmente cambian con menor frecuencia.
   */
  if (
    request.destination === "image" ||
    request.destination === "font"
  ) {
    event.respondWith(cacheFirst(request));
    return;
  }

  /*
   * Resto de archivos estáticos:
   * NETWORK FIRST
   */
  event.respondWith(networkFirst(request));
});

/* =========================================================
   ESTRATEGIA NETWORK FIRST
========================================================= */

async function networkFirst(request) {
  const cache = await caches.open(RUNTIME_CACHE);

  try {
    const response = await fetch(request, {
      cache: "no-store"
    });

    if (response && response.ok) {
      await cache.put(request, response.clone());
    }

    return response;
  } catch (error) {
    const cachedResponse = await cache.match(request);

    if (cachedResponse) {
      return cachedResponse;
    }

    return new Response("Recurso no disponible sin conexión.", {
      status: 503,
      headers: {
        "Content-Type": "text/plain; charset=UTF-8"
      }
    });
  }
}

/* =========================================================
   ESTRATEGIA CACHE FIRST
========================================================= */

async function cacheFirst(request) {
  const cache = await caches.open(RUNTIME_CACHE);
  const cachedResponse = await cache.match(request);

  if (cachedResponse) {
    return cachedResponse;
  }

  try {
    const response = await fetch(request);

    if (response && response.ok) {
      await cache.put(request, response.clone());
    }

    return response;
  } catch (error) {
    return new Response("Recurso no disponible.", {
      status: 503,
      headers: {
        "Content-Type": "text/plain; charset=UTF-8"
      }
    });
  }
}