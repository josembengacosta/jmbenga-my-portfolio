/**
 * JMbenga Portfolio — visitor-tracker.js
 * Rastreia a sessão do visitante em background.
 * Chama api/visitor.php via fetch sem bloquear o UI.
 * Inclui tempo na página e scroll depth.
 */

'use strict';

(function VisitorTracker() {
  // Não rastrear bots ou previews
  if (/bot|crawler|spider|prerender|headless/i.test(navigator.userAgent)) return;

  const BASE_URL = document.documentElement.dataset.baseUrl || '';
  const endpoint = BASE_URL + '/api/visitor';

  // Dados recolhidos
  const data = {
    page_url:     location.href,
    page_title:   document.title,
    referrer:     document.referrer || '',
    screen_w:     screen.width,
    screen_h:     screen.height,
    timezone:     Intl.DateTimeFormat().resolvedOptions().timeZone || '',
    language:     navigator.language || '',
    // UTM params
    utm_source:   _utm('utm_source'),
    utm_medium:   _utm('utm_medium'),
    utm_campaign: _utm('utm_campaign'),
    event:        'pageview',
  };

  function _utm(key) {
    return new URLSearchParams(location.search).get(key) || '';
  }

  // ── Enviar evento ──────────────────────────────────────────
  function send(extra = {}) {
    const payload = Object.assign({}, data, extra);
    try {
      // Preferir sendBeacon (garante envio mesmo ao fechar tab)
      if (navigator.sendBeacon) {
        const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
        navigator.sendBeacon(endpoint, blob);
      } else {
        fetch(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
          keepalive: true,
        }).catch(() => {});
      }
    } catch (_) {}
  }

  // ── Pageview inicial ───────────────────────────────────────
  const startTime = Date.now();
  send({ event: 'pageview' });

  // ── Scroll depth ───────────────────────────────────────────
  let maxScroll = 0;
  let scrollSent = { 25: false, 50: false, 75: false, 90: false };

  window.addEventListener('scroll', function () {
    const scrolled = Math.round(
      (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100
    );
    if (scrolled > maxScroll) maxScroll = scrolled;

    [25, 50, 75, 90].forEach(depth => {
      if (!scrollSent[depth] && maxScroll >= depth) {
        scrollSent[depth] = true;
        send({ event: 'scroll_depth', scroll_pct: depth });
      }
    });
  }, { passive: true });

  // ── Tempo na página + page_exit ────────────────────────────
  window.addEventListener('beforeunload', function () {
    const timeOnPage = Math.round((Date.now() - startTime) / 1000);
    send({ event: 'exit', time_on_page: timeOnPage, max_scroll: maxScroll });
  });

  // ── Presença online: ping a cada 30s (tab activa) ──────────
  let pingInterval;

  function startPing() {
    pingInterval = setInterval(() => {
      send({ event: 'ping', time_on_page: Math.round((Date.now() - startTime) / 1000) });
    }, 30000);
  }

  function stopPing() { clearInterval(pingInterval); }

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) { stopPing(); }
    else                 { startPing(); send({ event: 'tab_focus' }); }
  });

  startPing();
})();