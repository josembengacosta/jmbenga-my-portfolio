/**
 * JMbenga Portfolio — visitor-tracker.js
 * Regista pageviews, profundidade de scroll, tempo na página e presença.
 * Mantém uma fila temporária quando o visitante está offline ou a API falha.
 */

'use strict';

(function VisitorTracker() {
  // Não rastrear bots ou previews.
  if (/bot|crawler|spider|prerender|headless/i.test(navigator.userAgent)) return;

  const BASE_URL = document.documentElement.dataset.baseUrl || '';
  const endpoint = BASE_URL + '/api/visitor';
  const STORAGE_KEY = 'jmbenga_visitor_pending_v1';
  const MAX_PENDING = 40;

  function _utm(key) {
    return new URLSearchParams(location.search).get(key) || '';
  }

  // Dados comuns a todos os eventos desta página.
  const data = {
    page_url: location.href,
    page_title: document.title,
    referrer: document.referrer || '',
    screen_w: screen.width,
    screen_h: screen.height,
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
    language: navigator.language || '',
    utm_source: _utm('utm_source'),
    utm_medium: _utm('utm_medium'),
    utm_campaign: _utm('utm_campaign'),
    event: 'pageview',
  };

  function loadPending() {
    try {
      const stored = sessionStorage.getItem(STORAGE_KEY);
      const parsed = stored ? JSON.parse(stored) : [];
      return Array.isArray(parsed) ? parsed.filter(item => item && typeof item === 'object') : [];
    } catch (_) {
      return [];
    }
  }

  let pending = loadPending();
  let flushing = false;

  function savePending() {
    try {
      if (pending.length) {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(pending));
      } else {
        sessionStorage.removeItem(STORAGE_KEY);
      }
    } catch (_) {
      // O rastreamento não deve interromper a página se o armazenamento estiver bloqueado.
    }
  }

  function enqueue(payload) {
    // Evita acumular pings repetidos durante períodos longos sem ligação.
    if (payload.event === 'ping') {
      const oldPing = pending.findIndex(item => item.event === 'ping');
      if (oldPing !== -1) pending.splice(oldPing, 1);
    }

    if (pending.length >= MAX_PENDING) {
      // Mantém os eventos mais importantes, descartando primeiro pings/focos antigos.
      let discardIndex = pending.findIndex(item => item.event === 'ping' || item.event === 'tab_focus');
      if (discardIndex === -1) discardIndex = 0;
      pending.splice(discardIndex, 1);
    }

    pending.push(payload);
    savePending();
  }

  async function transmit(payload) {
    // navigator.onLine é apenas indicativo; o catch abaixo cobre falhas reais de rede.
    if (!navigator.onLine) return false;

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        keepalive: true,
      });
      return response.ok;
    } catch (_) {
      // Captura falhas de rede, incluindo ERR_INTERNET_DISCONNECTED.
      return false;
    }
  }

  async function flushPending() {
    if (flushing || !navigator.onLine || pending.length === 0) return;

    flushing = true;
    try {
      while (pending.length > 0 && navigator.onLine) {
        const item = pending[0];
        const delivered = await transmit(item);
        if (!delivered) break;
        pending.shift();
        savePending();
      }
    } finally {
      flushing = false;
    }
  }

  // Enviar evento sem bloquear a interface; colocar em fila quando a rede falha.
  function send(extra = {}) {
    const payload = Object.assign({}, data, extra);

    // Preserva a ordem dos eventos já pendentes.
    if (pending.length > 0 || !navigator.onLine) {
      enqueue(payload);
      flushPending();
      return;
    }

    // No encerramento, sendBeacon é mais adequado porque não depende da página continuar aberta.
    if (payload.event === 'exit' && typeof navigator.sendBeacon === 'function') {
      try {
        const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
        if (navigator.sendBeacon(endpoint, blob)) return;
      } catch (_) {
        // Continua para a tentativa fetch abaixo.
      }
    }

    transmit(payload).then(delivered => {
      if (!delivered) {
        enqueue(payload);
      }
    });
  }

  // Quando a ligação voltar, tenta enviar os eventos pendentes.
  window.addEventListener('online', flushPending);

  // ── Pageview inicial ────────────────────────────────────────
  const startTime = Date.now();
  flushPending();
  send({ event: 'pageview' });

  // ── Scroll depth ────────────────────────────────────────────
  let maxScroll = 0;
  const scrollSent = { 25: false, 50: false, 75: false, 90: false };

  window.addEventListener('scroll', function () {
    const scrollableHeight = document.documentElement.scrollHeight - window.innerHeight;
    if (scrollableHeight <= 0) return;

    const scrolled = Math.min(100, Math.max(0, Math.round((window.scrollY / scrollableHeight) * 100)));
    if (scrolled > maxScroll) maxScroll = scrolled;

    [25, 50, 75, 90].forEach(depth => {
      if (!scrollSent[depth] && maxScroll >= depth) {
        scrollSent[depth] = true;
        send({ event: 'scroll_depth', scroll_pct: depth });
      }
    });
  }, { passive: true });

  // ── Tempo na página + page_exit ─────────────────────────────
  window.addEventListener('pagehide', function () {
    const timeOnPage = Math.round((Date.now() - startTime) / 1000);
    send({ event: 'exit', time_on_page: timeOnPage, max_scroll: maxScroll });
  }, { once: true });

  // ── Presença online: ping a cada 30s enquanto a aba está visível ──
  let pingInterval = null;

  function startPing() {
    if (pingInterval !== null || document.hidden) return;
    pingInterval = window.setInterval(() => {
      send({ event: 'ping', time_on_page: Math.round((Date.now() - startTime) / 1000) });
    }, 30000);
  }

  function stopPing() {
    if (pingInterval !== null) {
      window.clearInterval(pingInterval);
      pingInterval = null;
    }
  }

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      stopPing();
    } else {
      startPing();
      send({ event: 'tab_focus' });
      flushPending();
    }
  });

  startPing();
})();
