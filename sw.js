// ============================================
// SERVICE WORKER - PORTFÓLIO JOSÉ MBENDA (OTIMIZADO)
// ============================================

const APP_VERSION = "2.1";
const CACHE_NAME = `jmbenga-pwa-v${APP_VERSION.replace(/\./g, "-")}`;

// ========== CACHE STRATEGIES ==========

// 1. CACHE IMMEDIATELY - Recursos essenciais
const IMMEDIATE_CACHE = [
  // HTML Core
  "index.html",
  "offline.html",

  // Manifest & Icons
  "manifest.php",
  "assets/img/icons/icon-192x192.png",
  "assets/img/icons/icon-512x512.png",
  "assets/img/svg/favicon_jm.ico",

  // Core CSS
  "assets/css/main.css",
  "assets/css/improvements.css",
  "assets/css/particles.css",

  // Core JS
  "assets/js/main.js",
  "assets/js/improvements.js",

  // Fonts (Font Awesome)
  "assets/fontawesome-free/webfonts/fa-solid-900.woff2",
  "assets/fontawesome-free/webfonts/fa-brands-400.woff2",
];

// 2. CACHE LAZY - Recursos importantes (cache após instalação)
const LAZY_CACHE = [
  // Other HTML pages
  "pages/all-projects.html",
  "pages/project-demo.html",

  // Additional CSS
  "assets/css/particles.css",
  "assets/css/all-projects.css",
  "assets/css/project-demo.css",

  // Additional JS
  "assets/js/pwa-install.js",
  "assets/js/pwa-native.js",
  "assets/js/project-demo.js",
  "assets/js/all-projects.js",

  // Important images
  "assets/img/photo/josembengadacosta.png",
];

// 3. EXTERNAL RESOURCES
const EXTERNAL_RESOURCES = [
  "https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap",
  "https://unpkg.com/aos@2.3.1/dist/aos.css",
  "https://unpkg.com/aos@2.3.1/dist/aos.js",
  "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css",
];

// 4. NETWORK ONLY - Nunca cache
const NETWORK_ONLY = [
  // Endpoints/API GET que não devem ser guardados em cache.
  // NÃO incluir contact-form.js: é um asset estático e deve funcionar offline.
  /\/submit/i,
  /\/api\//i,
];

// 5. PATTERNS FOR DYNAMIC CACHING
const PATTERNS = {
  html: /\.html$/i,
  css: /\.css$/i,
  js: /\.js$/i,
  images: /\.(png|jpg|jpeg|gif|svg|ico|webp)$/i,
  fonts: /\.(woff|woff2|ttf|eot)$/i,
  pdf: /\.pdf$/i,
};

// ========== INSTALL - Cache Essential Resources ==========

self.addEventListener("install", (event) => {
  console.log(`SW v${APP_VERSION} a instalar...`);

  event.waitUntil((async () => {
    const cache = await caches.open(CACHE_NAME);

    // A página offline é guardada separadamente. A falha de outro asset não
    // deve impedir o armazenamento deste fallback essencial.
    await cacheOfflinePage(cache);

    const immediateResources = IMMEDIATE_CACHE.filter(
      (path) => path !== "offline.html"
    );

    const criticalScripts = [
      "assets/js/contact-form.js",
      "assets/js/visitor-tracker.js",
    ];

    const resources = [...immediateResources, ...criticalScripts];
    await Promise.allSettled(resources.map(async (path) => {
      const url = appUrl(path);
      try {
        await cache.add(url);
      } catch (error) {
        console.warn(`Recurso não cacheado: ${url}`, error);
      }
    }));

    console.log("Instalação do Service Worker concluída.");
    await self.skipWaiting();
  })());
});

// ========== ACTIVATE - Cleanup & Claim ==========

self.addEventListener("activate", (event) => {
  console.log(` SW v${APP_VERSION} ativado`);

  event.waitUntil(
    Promise.all([
      // Clean up old caches
      caches.keys().then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            if (cacheName !== CACHE_NAME && cacheName.startsWith("jmbenga-")) {
              console.log(`🗑️ Removendo cache antigo: ${cacheName}`);
              return caches.delete(cacheName);
            }
          })
        );
      }),

      // Take control immediately
      self.clients.claim(),

      // Cache lazy resources in background
      cacheLazyResources(),
    ])
  );
});

// ========== FETCH - Intelligent Caching Strategy ==========

self.addEventListener("fetch", (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET requests
  if (request.method !== "GET") return;

  // Skip browser extensions and chrome:// URLs
  if (url.protocol === "chrome-extension:" || url.protocol === "chrome:")
    return;

  // Skip data URLs
  if (url.protocol === "data:") return;

  // Determine strategy based on URL
  const strategy = getStrategy(url, request);

  // Apply strategy
  switch (strategy) {
    case "network-only":
      event.respondWith(networkOnly(request));
      break;

    case "cache-first":
      event.respondWith(cacheFirst(request));
      break;

    case "stale-while-revalidate":
      event.respondWith(staleWhileRevalidate(request, event));
      break;

    case "network-first":
    default:
      event.respondWith(networkFirst(request));
      break;
  }
});

// ========== STRATEGY DETECTION ==========

function getStrategy(url, request) {
  // Network only patterns
  for (const pattern of NETWORK_ONLY) {
    if (pattern.test(url.pathname)) {
      return "network-only";
    }
  }

  // External resources
  if (url.origin !== self.location.origin) {
    return "stale-while-revalidate";
  }

  // Navigation (HTML pages)
  if (request.mode === "navigate") {
    return "network-first";
  }

  // Static assets
  if (PATTERNS.images.test(url.pathname)) {
    return "cache-first";
  }

  if (PATTERNS.css.test(url.pathname)) {
    return "stale-while-revalidate";
  }

  if (PATTERNS.js.test(url.pathname)) {
    return "cache-first";
  }

  if (PATTERNS.fonts.test(url.pathname)) {
    return "cache-first";
  }

  if (PATTERNS.pdf.test(url.pathname)) {
    return "network-first";
  }

  // Default: network first
  return "network-first";
}

// ========== CACHING STRATEGIES IMPLEMENTATION ==========

// 1. NETWORK ONLY
async function networkOnly(request) {
  try {
    return await fetch(request);
  } catch (error) {
    // Evita que a promessa de respondWith() seja rejeitada quando estiver offline.
    // APIs GET permanecem sem cache; a resposta apenas torna a falha explícita.
    return new Response(
      JSON.stringify({
        success: false,
        error: "offline",
        message: "Sem ligação à rede. Tenta novamente quando a ligação regressar.",
      }),
      {
        status: 503,
        headers: {
          "Content-Type": "application/json; charset=utf-8",
          "Cache-Control": "no-store",
        },
      }
    );
  }
}

// 2. NETWORK FIRST (with offline fallback)
async function networkFirst(request) {
  try {
    // Try network first
    const networkResponse = await fetch(request);

    // Guarda apenas respostas bem-sucedidas que não proíbem cache.
    if (shouldCacheResponse(networkResponse)) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, networkResponse.clone()).catch((err) => {
        console.warn("Não foi possível guardar a resposta em cache:", err);
      });
    }

    return networkResponse;
  } catch (error) {
    // Network failed, try cache
    console.log(" Offline, buscando no cache:", request.url);

    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }

    // If it's a page request and no cache, show offline page
    if (request.mode === "navigate") {
      return getOfflinePage();
    }

    // For other requests, return error
    return new Response("Sem ligação à rede.", {
      status: 503,
      headers: {
        "Content-Type": "text/plain; charset=utf-8",
        "Cache-Control": "no-store",
      },
    });
  }
}

// 3. CACHE FIRST (for static assets)
async function cacheFirst(request) {
  // Try cache first
  const cachedResponse = await caches.match(request);
  if (cachedResponse) {
    // Update cache in background
    updateCacheInBackground(request);
    return cachedResponse;
  }

  // Not in cache, try network
  try {
    const networkResponse = await fetch(request);

    if (shouldCacheResponse(networkResponse)) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, networkResponse.clone()).catch((err) => {
        console.warn("Não foi possível guardar o asset em cache:", err);
      });
    }

    return networkResponse;
  } catch (error) {
    // Network failed, no cache
    if (PATTERNS.images.test(request.url)) {
      // Return placeholder for images
      return new Response(
        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
          <rect width="100" height="100" fill="#f1f5f9"/>
          <text x="50" y="50" text-anchor="middle" fill="#64748b">Image</text>
        </svg>`,
        {
          headers: { "Content-Type": "image/svg+xml" },
        }
      );
    }

    return new Response("", { status: 404 });
  }
}

// 4. STALE WHILE REVALIDATE (CSS e recursos externos)
async function staleWhileRevalidate(request, event) {
  const cachePromise = caches.open(CACHE_NAME);

  // Inicia a atualização antes do primeiro await e prolonga a vida do evento.
  const updatePromise = cachePromise.then(async (cache) => {
    const response = await fetch(request);
    if (shouldCacheResponse(response)) {
      cache.put(request, response.clone()).catch((error) => {
        console.warn("Não foi possível atualizar o cache:", error);
      });
    }
    return response;
  });

  if (event && typeof event.waitUntil === "function") {
    event.waitUntil(updatePromise.then(() => undefined).catch(() => undefined));
  }

  const cache = await cachePromise;
  const cachedResponse = await cache.match(request);
  if (cachedResponse) return cachedResponse;

  try {
    return await updatePromise;
  } catch (error) {
    return new Response("Recurso indisponível sem ligação à rede.", {
      status: 503,
      headers: {
        "Content-Type": "text/plain; charset=utf-8",
        "Cache-Control": "no-store",
      },
    });
  }
}

// ========== HELPER FUNCTIONS ==========

/** Resolve o caminho dentro do âmbito real do Service Worker.
 * Funciona tanto na raiz do domínio como em /portfolio-jmbenga/ no XAMPP.
 */
function appUrl(path) {
  const relativePath = String(path).replace(/^\/+/, "");
  return new URL(relativePath, self.registration.scope).href;
}

function shouldCacheResponse(response) {
  if (!response || !response.ok || response.type === "opaqueredirect") return false;
  const cacheControl = response.headers.get("Cache-Control") || "";
  return !/\bno-store\b/i.test(cacheControl);
}

function offlineFallbackResponse() {
  return new Response(
    `<!doctype html>
<html lang="pt-AO">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0a0d14">
  <title>Offline | JMbenga</title>
  <style>
    *{box-sizing:border-box}body{margin:0;padding:24px;min-height:100vh;display:grid;place-items:center;background:#0a0d14;color:#e8edf5;font:16px/1.65 system-ui,sans-serif;text-align:center}
    main{width:min(100%,520px)}.icon{font-size:3rem;color:#60a5fa}h1{font-size:clamp(1.7rem,5vw,2.3rem);color:#60a5fa;margin:.5rem 0}p{color:#a7b4ca}.btn{display:inline-block;margin-top:12px;padding:12px 20px;border-radius:10px;background:#2563eb;color:white;text-decoration:none;border:0;font:inherit;cursor:pointer}.btn:focus-visible{outline:3px solid #93c5fd;outline-offset:3px}
  </style>
</head>
<body><main><div class="icon" aria-hidden="true">⌁</div><h1>Estás offline</h1><p>Não foi possível estabelecer ligação ao servidor. Verifica a tua ligação à Internet e tenta novamente.</p><button class="btn" type="button" onclick="location.reload()">Tentar novamente</button></main></body>
</html>`,
    {
      status: 200,
      headers: {
        "Content-Type": "text/html; charset=utf-8",
        "Cache-Control": "no-store",
        "X-Offline-Mode": "true",
      },
    }
  );
}

async function cacheOfflinePage(cache) {
  const offlineUrl = appUrl("offline.html");

  try {
    const response = await fetch(offlineUrl, { cache: "reload" });
    if (!response.ok) {
      throw new Error(`HTTP ${response.status} ao obter offline.html`);
    }
    await cache.put(offlineUrl, response.clone());
    console.log("Página offline personalizada guardada:", offlineUrl);
  } catch (error) {
    const existing = await cache.match(offlineUrl);
    if (existing) {
      console.warn("Não foi possível atualizar offline.html; a versão em cache será mantida.", error);
      return;
    }

    await cache.put(offlineUrl, offlineFallbackResponse());
    console.warn("Foi guardada a página offline de reserva porque offline.html não pôde ser obtida.", error);
  }
}

async function cacheLazyResources() {
  try {
    const cache = await caches.open(CACHE_NAME);
    const resourcesToCache = LAZY_CACHE.map(appUrl);
    const batchSize = 5;

    for (let i = 0; i < resourcesToCache.length; i += batchSize) {
      const batch = resourcesToCache.slice(i, i + batchSize);
      await Promise.allSettled(
        batch.map(async (url) => {
          try {
            await cache.add(url);
          } catch (error) {
            console.log(`Recurso secundário não cacheado: ${url}`, error);
          }
        })
      );
    }

    console.log("Cache secundário concluído.");
  } catch (error) {
    console.error("Erro ao guardar recursos secundários em cache:", error);
  }
}

async function updateCacheInBackground(request) {
  try {
    const response = await fetch(request);
    if (!shouldCacheResponse(response)) return;
    const cache = await caches.open(CACHE_NAME);
    await cache.put(request, response.clone());
  } catch (error) {
    // A cópia em cache continua disponível mesmo se a atualização falhar.
  }
}

async function getOfflinePage() {
  const cache = await caches.open(CACHE_NAME);
  const offlineUrl = appUrl("offline.html");
  const offlinePage = await cache.match(offlineUrl);

  if (offlinePage) return offlinePage;

  // Última salvaguarda: cria e guarda uma página autónoma, sem recursos externos.
  const fallback = offlineFallbackResponse();
  await cache.put(offlineUrl, fallback.clone()).catch((error) => {
    console.warn("Não foi possível guardar a página offline de reserva:", error);
  });
  return fallback;
}

// ========== MESSAGE HANDLING ==========

self.addEventListener("message", (event) => {
  const { type } = event.data || {};
  const reply = (message) => {
    if (event.source && typeof event.source.postMessage === "function") {
      event.source.postMessage(message);
    }
  };

  switch (type) {
    case "GET_VERSION":
      reply({ type: "VERSION_INFO", version: APP_VERSION, cacheName: CACHE_NAME });
      break;

    case "CLEAR_CACHE":
      event.waitUntil(
        caches.delete(CACHE_NAME)
          .then(() => reply({ type: "CACHE_CLEARED", success: true }))
          .catch((error) => reply({
            type: "CACHE_CLEARED",
            success: false,
            error: error.message,
          }))
      );
      break;

    case "UPDATE_SW":
      event.waitUntil(
        self.skipWaiting().then(() => {
          reply({ type: "SW_UPDATED", success: true });
        }).catch((error) => {
          reply({ type: "SW_UPDATED", success: false, error: error.message });
        })
      );
      break;
  }
});

// ========== PUSH NOTIFICATIONS (Optional) ==========

function safeNotificationUrl(candidate) {
  try {
    const target = new URL(candidate || appUrl(""), self.location.origin);
    if (target.origin !== self.location.origin) return appUrl("");
    return target.href;
  } catch (error) {
    return appUrl("");
  }
}

self.addEventListener("push", (event) => {
  let data = {};
  if (event.data) {
    try {
      data = event.data.json() || {};
    } catch (error) {
      data = { body: event.data.text() };
    }
  }

  const options = {
    body: data.body || "Novo conteúdo disponível no meu portefólio!",
    icon: appUrl("assets/img/icons/icon-192x192.png"),
    badge: appUrl("assets/img/icons/icon-72x72.png"),
    vibrate: [200, 100, 200],
    data: {
      url: safeNotificationUrl(data.url),
      date: new Date().toISOString(),
    },
    actions: [
      { action: "open", title: "Abrir" },
      { action: "close", title: "Fechar" },
    ],
  };

  event.waitUntil(
    self.registration.showNotification("JMbenga Portfolio", options)
  );
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  if (event.action === "close") return;

  const targetUrl = safeNotificationUrl(
    event.notification.data && event.notification.data.url
  );

  event.waitUntil((async () => {
    const windows = await self.clients.matchAll({
      type: "window",
      includeUncontrolled: true,
    });

    for (const client of windows) {
      if (new URL(client.url).origin === self.location.origin && "focus" in client) {
        if (client.url !== targetUrl && "navigate" in client) {
          await client.navigate(targetUrl);
        }
        return client.focus();
      }
    }

    return self.clients.openWindow(targetUrl);
  })());
});

// ========== CONSOLE LOG ==========

console.log(` Service Worker v${APP_VERSION} carregado e pronto!`);
console.log(` Cache: ${CACHE_NAME}`);
console.log(` Host: ${self.location.hostname}`);