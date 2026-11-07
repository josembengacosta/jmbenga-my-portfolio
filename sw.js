/*
 * JMbenga Portfolio — Service Worker
 * PWA cache strategy v3.0.0
 *
 * - Cacheia apenas recursos reais e páginas públicas seleccionadas.
 * - Nunca guarda páginas do painel, endpoints API ou downloads privados.
 * - Distingue falha de uma página de indisponibilidade do servidor.
 * - Usa um teste real ao endpoint PHP do próprio site; navigator.onLine
 *   não é tratado como prova suficiente de conectividade.
 */

const APP_VERSION = "3.0.0";
const CACHE_PREFIX = "jmbenga-";
const VERSION_SUFFIX = APP_VERSION.replace(/\./g, "-");
const STATIC_CACHE = `${CACHE_PREFIX}static-v${VERSION_SUFFIX}`;
const PAGE_CACHE = `${CACHE_PREFIX}pages-v${VERSION_SUFFIX}`;
const RUNTIME_CACHE = `${CACHE_PREFIX}runtime-v${VERSION_SUFFIX}`;
const CURRENT_CACHES = [STATIC_CACHE, PAGE_CACHE, RUNTIME_CACHE];

const OFFLINE_PATH = "offline.html";
const PROBE_PATH = "manifest.php";
const PROBE_PARAM = "__pwa_probe";
const PROBE_TIMEOUT_MS = 4500;
const MAX_PAGE_CACHE_ENTRIES = 30;
const MAX_RUNTIME_CACHE_ENTRIES = 100;

// Todos estes ficheiros existem no pacote recebido e são recursos públicos.
const PRECACHE_ASSETS = [
  "assets/img/icons/icon-192x192.png",
  "assets/img/icons/icon-512x512.png",
  "assets/img/icons/apple-touch-icon-180x180.png",
  "assets/img/icons/favicon.ico",
  "assets/css/main.css",
  "assets/css/animations.css",
  "assets/css/home.css",
  "assets/js/main.js",
  "assets/js/home.js",
  "assets/js/contact-form.js",
  "assets/js/visitor-tracker.js",
  "assets/js/pwa-install.js",
  "assets/js/pwa-native.js",
  "assets/js/app-util.js",
  "assets/js/project-detail.js",
];

const NETWORK_ONLY_PATTERNS = [
  /\/api(?:\/|$)/i,
  /\/submit(?:\/|$)/i,
];

const PRIVATE_PATH_PATTERNS = [
  /^jm-panel(?:\/|$)/i,
  /^download(?:\/|$)/i,
  /^status(?:\/|$)/i,
  /^database(?:\/|$)/i,
  /^includes(?:\/|$)/i,
];

const SENSITIVE_QUERY_KEYS = [
  "token",
  "reset_token",
  "invite_token",
  "access_code",
  "code",
  "key",
  "state",
];

const PUBLIC_NAVIGATION_PATTERNS = [
  /^$/i,
  /^home\/?$/i,
  /^about\/?$/i,
  /^contact\/?$/i,
  /^feedback\/?$/i,
  /^projects\/?$/i,
  /^project\/[a-z0-9-]+\/?$/i,
  /^preview\/[a-z0-9-]+\/?$/i,
  /^faq\/?$/i,
  /^cv\/?$/i,
  /^skills\/?$/i,
  /^servicos\/?$/i,
  /^services\/(mobile-apps|web-development|api-backend|ui-ux-design|maintenance)\/?$/i,
];

const FILE_PATTERNS = {
  css: /\.css$/i,
  js: /\.js$/i,
  images: /\.(png|jpe?g|gif|svg|ico|webp|avif)$/i,
  fonts: /\.(woff2?|ttf|otf|eot)$/i,
};

function appUrl(path) {
  const cleanPath = String(path || "").replace(/^\/+/, "");
  return new URL(cleanPath, self.registration.scope).href;
}

function scopeRelativePath(url) {
  const scopePath = new URL(self.registration.scope).pathname;
  if (!url.pathname.startsWith(scopePath)) return null;
  return url.pathname.slice(scopePath.length).replace(/^\/+/, "");
}

function isProbeRequest(url) {
  return url.searchParams.has(PROBE_PARAM);
}

function isPrivateRequest(url) {
  const relativePath = scopeRelativePath(url);
  if (relativePath === null) return true;

  if (PRIVATE_PATH_PATTERNS.some((pattern) => pattern.test(relativePath))) {
    return true;
  }

  return SENSITIVE_QUERY_KEYS.some((key) => url.searchParams.has(key));
}

function isPublicNavigation(url, request) {
  if (request.mode !== "navigate" || url.search !== "") return false;
  const relativePath = scopeRelativePath(url);
  if (relativePath === null) return false;
  return PUBLIC_NAVIGATION_PATTERNS.some((pattern) => pattern.test(relativePath));
}

function shouldCacheResponse(response, request, options = {}) {
  if (!response) return false;
  if (response.type === "opaqueredirect") return false;

  // Respostas opacas só podem ser guardadas para recursos externos estáticos.
  if (response.type === "opaque") return options.allowOpaque === true;
  if (!response.ok) return false;

  const cacheControl = response.headers.get("Cache-Control") || "";
  if (/\b(no-store|private)\b/i.test(cacheControl)) return false;

  const vary = response.headers.get("Vary") || "";
  if (vary.trim() === "*") return false;

  if (options.navigation) {
    const contentType = response.headers.get("Content-Type") || "";
    if (!contentType.toLowerCase().includes("text/html")) return false;
    if (response.status !== 200 || response.redirected) return false;
  }

  return true;
}

async function trimCache(cacheName, maxEntries) {
  const cache = await caches.open(cacheName);
  const keys = await cache.keys();
  if (keys.length <= maxEntries) return;

  const removeCount = keys.length - maxEntries;
  await Promise.all(keys.slice(0, removeCount).map((key) => cache.delete(key)));
}

async function saveResponse(cacheName, request, response, maxEntries) {
  const cache = await caches.open(cacheName);
  await cache.put(request, response.clone());
  await trimCache(cacheName, maxEntries);
}

async function precacheAsset(path) {
  const url = appUrl(path);
  try {
    const request = new Request(url, {
      method: "GET",
      credentials: "same-origin",
      cache: "reload",
    });
    const response = await fetch(request);
    if (!shouldCacheResponse(response, request)) {
      throw new Error(`Resposta não cacheável: HTTP ${response.status} — ${url}`);
    }
    const cache = await caches.open(STATIC_CACHE);
    await cache.put(request, response.clone());
  } catch (error) {
    // Um recurso secundário em falta não deve impedir a instalação do PWA.
    console.warn("PWA: recurso não incluído no cache inicial:", url, error);
  }
}

async function cacheOfflinePage() {
  const cache = await caches.open(STATIC_CACHE);
  const offlineUrl = appUrl(OFFLINE_PATH);

  // Garante um fallback válido mesmo se a primeira leitura da página falhar.
  await cache.put(offlineUrl, offlineFallbackResponse());

  try {
    const response = await fetch(offlineUrl, { cache: "reload" });
    if (response.ok) {
      await cache.put(offlineUrl, response.clone());
    }
  } catch (error) {
    console.warn("PWA: será utilizada a página offline de reserva.", error);
  }
}

async function cacheHomePage() {
  // A rota inicial é guardada durante a instalação, mesmo antes de a página
  // actual passar a ser controlada pelo novo Service Worker.
  try {
    const homeRequest = new Request(appUrl("home"), {
      method: "GET",
      credentials: "same-origin",
      headers: { Accept: "text/html" },
      cache: "reload",
    });
    const response = await fetch(homeRequest);
    if (!shouldCacheResponse(response, homeRequest, { navigation: true })) return;
    await saveResponse(PAGE_CACHE, homeRequest, response, MAX_PAGE_CACHE_ENTRIES);
    console.info("PWA: página inicial guardada para utilização offline.");
  } catch (error) {
    console.warn("PWA: não foi possível pré-cachear a página inicial.", error);
  }
}

self.addEventListener("install", (event) => {
  event.waitUntil(
    (async () => {
      await cacheOfflinePage();
      await Promise.allSettled([
        ...PRECACHE_ASSETS.map(precacheAsset),
        cacheHomePage(),
      ]);
      await self.skipWaiting();
      console.info(`JMbenga PWA ${APP_VERSION}: instalação concluída.`);
    })(),
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    (async () => {
      const cacheNames = await caches.keys();
      await Promise.all(
        cacheNames
          .filter((name) => name.startsWith(CACHE_PREFIX) && !CURRENT_CACHES.includes(name))
          .map((name) => caches.delete(name)),
      );
      await self.clients.claim();
      console.info(`JMbenga PWA ${APP_VERSION}: activo.`);
    })(),
  );
});

self.addEventListener("fetch", (event) => {
  const request = event.request;
  if (request.method !== "GET") return;
  if (request.cache === "only-if-cached" && request.mode !== "same-origin") return;

  const url = new URL(request.url);
  if (url.protocol !== "http:" && url.protocol !== "https:") return;

  // O teste de conectividade precisa de chegar directamente ao servidor.
  if (isProbeRequest(url)) return;

  // Não interceptar o painel, downloads nem rotas privadas.
  if (url.origin === self.location.origin && isPrivateRequest(url)) return;

  const strategy = getStrategy(url, request);

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

function getStrategy(url, request) {
  if (NETWORK_ONLY_PATTERNS.some((pattern) => pattern.test(url.pathname))) {
    return "network-only";
  }

  if (url.origin !== self.location.origin) {
    return "stale-while-revalidate";
  }

  if (request.mode === "navigate") return "network-first";
  if (FILE_PATTERNS.images.test(url.pathname)) return "cache-first";
  if (FILE_PATTERNS.fonts.test(url.pathname)) return "cache-first";
  if (FILE_PATTERNS.css.test(url.pathname)) return "stale-while-revalidate";
  if (FILE_PATTERNS.js.test(url.pathname)) return "stale-while-revalidate";
  return "network-first";
}

async function networkOnly(request) {
  try {
    return await fetch(request);
  } catch (error) {
    return new Response(
      JSON.stringify({
        success: false,
        error: "network_unavailable",
        message: "Não foi possível contactar o serviço. Tenta novamente quando a ligação regressar.",
      }),
      {
        status: 503,
        headers: {
          "Content-Type": "application/json; charset=utf-8",
          "Cache-Control": "no-store",
          "X-PWA-Network": "unavailable",
        },
      },
    );
  }
}

async function networkFirst(request) {
  try {
    const response = await fetch(request);

    if (isPublicNavigation(new URL(request.url), request) && shouldCacheResponse(response, request, { navigation: true })) {
      await saveResponse(PAGE_CACHE, request, response, MAX_PAGE_CACHE_ENTRIES).catch((error) => {
        console.warn("PWA: não foi possível guardar a página:", error);
      });
    }

    return response;
  } catch (error) {
    // Primeiro tenta uma cópia da página pública previamente visitada.
    const cached = await caches.match(request);
    if (cached) return cached;

    if (request.mode === "navigate") {
      // Verifica o próprio PHP antes de afirmar que o utilizador está offline.
      const originReachable = await probeOrigin();
      return originReachable ? serviceUnavailableResponse() : getOfflinePage();
    }

    return new Response("Não foi possível carregar este recurso neste momento.", {
      status: 503,
      headers: {
        "Content-Type": "text/plain; charset=utf-8",
        "Cache-Control": "no-store",
        "X-PWA-Network": "unavailable",
      },
    });
  }
}

async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) {
    updateCacheInBackground(request);
    return cached;
  }

  try {
    const response = await fetch(request);
    if (shouldCacheResponse(response, request)) {
      await saveResponse(RUNTIME_CACHE, request, response, MAX_RUNTIME_CACHE_ENTRIES);
    }
    return response;
  } catch (error) {
    if (FILE_PATTERNS.images.test(new URL(request.url).pathname)) {
      return new Response(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#f1f5f9"/><text x="50" y="54" text-anchor="middle" font-family="sans-serif" font-size="8" fill="#64748b">Imagem indisponível</text></svg>',
        { headers: { "Content-Type": "image/svg+xml; charset=utf-8", "Cache-Control": "no-store" } },
      );
    }

    return new Response("Recurso temporariamente indisponível.", {
      status: 503,
      headers: { "Content-Type": "text/plain; charset=utf-8", "Cache-Control": "no-store" },
    });
  }
}

async function staleWhileRevalidate(request, event) {
  // Inicia a actualização e regista waitUntil antes de qualquer await.
  const updatePromise = fetch(request).then(async (response) => {
    if (shouldCacheResponse(response, request, { allowOpaque: true })) {
      await saveResponse(RUNTIME_CACHE, request, response, MAX_RUNTIME_CACHE_ENTRIES);
    }
    return response;
  });

  if (event && typeof event.waitUntil === "function") {
    event.waitUntil(updatePromise.then(() => undefined).catch(() => undefined));
  }

  // Procura também no cache estático, onde ficam os assets pré-cacheados.
  const cached = await caches.match(request);
  if (cached) return cached;

  try {
    return await updatePromise;
  } catch (error) {
    return new Response("Recurso externo indisponível sem ligação à rede.", {
      status: 503,
      headers: { "Content-Type": "text/plain; charset=utf-8", "Cache-Control": "no-store" },
    });
  }
}

async function updateCacheInBackground(request) {
  try {
    const response = await fetch(request);
    if (!shouldCacheResponse(response, request)) return;
    await saveResponse(RUNTIME_CACHE, request, response, MAX_RUNTIME_CACHE_ENTRIES);
  } catch (error) {
    // Mantém a cópia em cache; falhas de actualização em segundo plano são esperadas offline.
  }
}

async function probeOrigin() {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), PROBE_TIMEOUT_MS);

  try {
    const probeUrl = new URL(PROBE_PATH, self.registration.scope);
    probeUrl.searchParams.set(PROBE_PARAM, String(Date.now()));

    const response = await fetch(probeUrl.href, {
      method: "GET",
      cache: "no-store",
      credentials: "same-origin",
      headers: {
        Accept: "application/manifest+json",
        "Cache-Control": "no-cache",
      },
      signal: controller.signal,
    });

    const contentType = response.headers.get("Content-Type") || "";
    return response.ok && contentType.toLowerCase().includes("application/manifest+json");
  } catch (error) {
    return false;
  } finally {
    clearTimeout(timeoutId);
  }
}

async function getOfflinePage() {
  const cache = await caches.open(STATIC_CACHE);
  const offlineUrl = appUrl(OFFLINE_PATH);
  const cachedPage = await cache.match(offlineUrl);
  return cachedPage || offlineFallbackResponse();
}

function offlineFallbackResponse() {
  return new Response(
    `<!doctype html>
<html lang="pt-AO">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0a0d14">
  <title>Ligação indisponível | JMbenga</title>
  <style>
    *{box-sizing:border-box}body{margin:0;padding:24px;min-height:100vh;display:grid;place-items:center;background:#0a0d14;color:#e8edf5;font:16px/1.65 system-ui,sans-serif;text-align:center}
    main{width:min(100%,520px)}h1{font-size:clamp(1.7rem,5vw,2.3rem);color:#60a5fa;margin:.5rem 0}p{color:#a7b4ca}.btn{display:inline-block;margin-top:12px;padding:12px 20px;border-radius:10px;background:#2563eb;color:white;text-decoration:none;border:0;font:inherit;cursor:pointer}
  </style>
</head>
<body><main><h1>Não foi possível contactar o JMbenga</h1><p>A ligação à Internet pode estar indisponível ou o servidor pode estar temporariamente inacessível. Verifica a ligação e tenta novamente.</p><button class="btn" type="button" onclick="location.reload()">Tentar novamente</button></main></body>
</html>`,
    {
      status: 200,
      headers: {
        "Content-Type": "text/html; charset=utf-8",
        "Cache-Control": "no-store",
        "X-PWA-Network": "unavailable",
      },
    },
  );
}

function serviceUnavailableResponse() {
  return new Response(
    `<!doctype html><html lang="pt-AO"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Página temporariamente indisponível | JMbenga</title><style>body{margin:0;padding:24px;min-height:100vh;display:grid;place-items:center;background:#0a0d14;color:#e8edf5;font:16px/1.6 system-ui,sans-serif;text-align:center}main{max-width:520px}h1{color:#60a5fa}p{color:#a7b4ca}button{padding:12px 20px;border:0;border-radius:10px;background:#2563eb;color:#fff;font:inherit;cursor:pointer}</style></head><body><main><h1>Não foi possível carregar esta página</h1><p>O servidor está acessível, mas esta página não respondeu correctamente. Tenta novamente.</p><button onclick="location.reload()">Tentar novamente</button></main></body></html>`,
    {
      status: 503,
      headers: {
        "Content-Type": "text/html; charset=utf-8",
        "Cache-Control": "no-store",
        "X-PWA-Network": "origin-reachable",
      },
    },
  );
}

self.addEventListener("message", (event) => {
  const data = event.data || {};
  const reply = (message) => {
    if (event.source && typeof event.source.postMessage === "function") {
      event.source.postMessage(message);
    }
  };

  switch (data.type) {
    case "GET_VERSION":
      reply({ type: "VERSION_INFO", version: APP_VERSION, caches: CURRENT_CACHES });
      break;

    case "CLEAR_CACHE":
      event.waitUntil(
        Promise.all(CURRENT_CACHES.map((name) => caches.delete(name))).then(() => {
          reply({ type: "CACHE_CLEARED", success: true });
        }).catch((error) => {
          reply({ type: "CACHE_CLEARED", success: false, error: error.message });
        }),
      );
      break;

    case "UPDATE_SW":
      event.waitUntil(
        self.skipWaiting().then(() => reply({ type: "SW_UPDATED", success: true })),
      );
      break;
  }
});

function safeNotificationUrl(candidate) {
  try {
    const target = new URL(candidate || appUrl("home"), self.location.origin);
    if (target.origin !== self.location.origin) return appUrl("home");
    if (isPrivateRequest(target)) return appUrl("home");
    return target.href;
  } catch (error) {
    return appUrl("home");
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
    data: { url: safeNotificationUrl(data.url), date: new Date().toISOString() },
    actions: [
      { action: "open", title: "Abrir" },
      { action: "close", title: "Fechar" },
    ],
  };

  event.waitUntil(self.registration.showNotification("JMbenga Portfolio", options));
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  if (event.action === "close") return;

  const targetUrl = safeNotificationUrl(event.notification.data && event.notification.data.url);
  event.waitUntil((async () => {
    const windows = await self.clients.matchAll({ type: "window", includeUncontrolled: true });
    for (const client of windows) {
      if (new URL(client.url).origin === self.location.origin && "focus" in client) {
        if (client.url !== targetUrl && "navigate" in client) await client.navigate(targetUrl);
        return client.focus();
      }
    }
    return self.clients.openWindow(targetUrl);
  })());
});

console.info(`JMbenga Service Worker ${APP_VERSION} carregado.`);
