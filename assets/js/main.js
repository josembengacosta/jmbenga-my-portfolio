/**
 * JMbenga Portfolio — main.js
 * JavaScript global: carregado em todas as páginas através de includes/footer.php.
 * Responsável por navbar/offcanvas, tema, efeitos globais, skills, toasts e utilitários.
 */
"use strict";

(function initGlobalUI() {
  const root = document.documentElement;
  const body = document.body;
  const reduceMotion = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches ?? false;

  /* Loading */
  window.addEventListener("load", () => {
    window.setTimeout(() => {
      document.getElementById("loading")?.classList.add("hidden");
    }, reduceMotion ? 0 : 900);
  }, { once: true });

  /* Cursor personalizado: mantém transformações sem as apagar a cada frame. */
  (function initCursor() {
    const cursor = document.getElementById("cursor");
    const ring = document.getElementById("cursor-ring");
    if (!cursor || !ring) return;

    if (window.matchMedia("(pointer: coarse)").matches || reduceMotion) {
      cursor.style.display = "none";
      ring.style.display = "none";
      return;
    }

    let mx = -100, my = -100, rx = -100, ry = -100;
    document.addEventListener("mousemove", (event) => {
      mx = event.clientX;
      my = event.clientY;
    }, { passive: true });

    function tick() {
      rx += (mx - rx) * 0.12;
      ry += (my - ry) * 0.12;
      cursor.style.left = `${mx - 6}px`;
      cursor.style.top = `${my - 6}px`;
      ring.style.left = `${rx - 18}px`;
      ring.style.top = `${ry - 18}px`;
      window.requestAnimationFrame(tick);
    }
    window.requestAnimationFrame(tick);

    const hoverable = "a, button, input, textarea, select, [data-hover]";
    document.addEventListener("mouseover", (event) => {
      const target = event.target instanceof Element ? event.target : null;
      if (target?.closest(hoverable)) {
        cursor.style.transform = "scale(2.2)";
        ring.style.transform = "scale(1.6)";
      }
    });
    document.addEventListener("mouseout", (event) => {
      const target = event.target instanceof Element ? event.target : null;
      if (target?.closest(hoverable)) {
        cursor.style.transform = "";
        ring.style.transform = "";
      }
    });
  })();

  /* Scroll progress, header e back-to-top: um único listener global. */
  const scrollBar = document.getElementById("scroll-progress");
  const header = document.getElementById("header");
  const backTop = document.getElementById("back-top");

  function updateScrollUI() {
    const scrollY = window.scrollY || root.scrollTop || 0;
    const maxScroll = Math.max(0, root.scrollHeight - window.innerHeight);
    const percent = maxScroll > 0 ? Math.min(100, Math.max(0, (scrollY / maxScroll) * 100)) : 0;
    if (scrollBar) scrollBar.style.width = `${percent}%`;
    if (header) header.classList.toggle("scrolled", scrollY > 50);
    if (backTop) backTop.classList.toggle("visible", scrollY > 400);
  }
  window.addEventListener("scroll", updateScrollUI, { passive: true });
  window.addEventListener("resize", updateScrollUI, { passive: true });
  updateScrollUI();

  /* Navbar: a marcação active vem do PHP (rota actual); não a sobrescrever com hashes. */

  /* Offcanvas do navbar.php: #menuToggle, #offcanvas, #backdrop. */
  const menuToggle = document.getElementById("menuToggle");
  const offcanvas = document.getElementById("offcanvas");
  const backdrop = document.getElementById("backdrop");

  window.openMenu = function openMenu() {
    if (!offcanvas) return;
    offcanvas.classList.add("open");
    backdrop?.classList.add("open");
    menuToggle?.classList.add("open");
    menuToggle?.setAttribute("aria-expanded", "true");
    offcanvas.setAttribute("aria-hidden", "false");
    body.style.overflow = "hidden";
  };

  window.closeMenu = function closeMenu() {
    offcanvas?.classList.remove("open");
    backdrop?.classList.remove("open");
    menuToggle?.classList.remove("open");
    menuToggle?.setAttribute("aria-expanded", "false");
    offcanvas?.setAttribute("aria-hidden", "true");
    body.style.overflow = "";
  };

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && offcanvas?.classList.contains("open")) {
      window.closeMenu();
      menuToggle?.focus();
    }
  });
  offcanvas?.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => window.closeMenu());
  });

  /* Reveal on scroll (com fallback quando IntersectionObserver não existe). */
  function observeOrShow(elements, options, onVisible) {
    if (!elements.length) return;
    if (!("IntersectionObserver" in window)) {
      elements.forEach(onVisible);
      return;
    }
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        onVisible(entry.target);
        observer.unobserve(entry.target);
      });
    }, options);
    elements.forEach((element) => observer.observe(element));
  }

  observeOrShow(
    Array.from(document.querySelectorAll(".reveal")),
    { threshold: 0.08, rootMargin: "0px 0px -40px 0px" },
    (element) => element.classList.add("visible")
  );

  observeOrShow(
    Array.from(document.querySelectorAll(".timeline-item")),
    { threshold: 0.2 },
    (element) => element.classList.add("visible")
  );
  document.querySelectorAll(".timeline-item").forEach((element, index) => {
    element.style.transitionDelay = reduceMotion ? "0s" : `${index * 0.12}s`;
  });

  /* Contadores numéricos. */
  observeOrShow(
    Array.from(document.querySelectorAll(".counter")),
    { threshold: 0.5 },
    (element) => {
      const end = Number.parseInt(element.dataset.target || "0", 10);
      if (!Number.isFinite(end) || end <= 0 || reduceMotion) {
        element.textContent = String(Number.isFinite(end) ? Math.max(0, end) : 0);
        return;
      }
      let current = 0;
      const step = Math.max(1, Math.ceil(end / 60));
      const timer = window.setInterval(() => {
        current = Math.min(current + step, end);
        element.textContent = current.toLocaleString("pt-AO");
        if (current >= end) window.clearInterval(timer);
      }, 22);
    }
  );

  /* Barras de competências. */
  observeOrShow(
    Array.from(document.querySelectorAll(".skill-bar-fill")),
    { threshold: 0.3 },
    (bar) => {
      const raw = Number.parseFloat(bar.dataset.width || "0");
      const width = Number.isFinite(raw) ? Math.min(100, Math.max(0, raw)) : 0;
      bar.style.width = `${width}%`;
    }
  );

  /* Tabs: suporta ambos os nomes usados nas versões anteriores do site. */
  window.switchSkillTab = function switchSkillTab(category) {
    document.querySelectorAll(".skill-tab, .skills-tab").forEach((tab) => {
      tab.classList.toggle("active", tab.dataset.tab === category);
      tab.setAttribute("aria-selected", String(tab.dataset.tab === category));
    });
    document.querySelectorAll(".skills-panel").forEach((panel) => {
      panel.classList.toggle("active", panel.id === `tab-${category}`);
      panel.hidden = panel.id !== `tab-${category}`;
    });
    document.querySelectorAll(`#tab-${CSS.escape(category)} .skill-bar-fill`).forEach((bar) => {
      const raw = Number.parseFloat(bar.dataset.width || "0");
      const width = Number.isFinite(raw) ? Math.min(100, Math.max(0, raw)) : 0;
      if (reduceMotion) {
        bar.style.width = `${width}%`;
      } else {
        bar.style.width = "0%";
        window.requestAnimationFrame(() => { bar.style.width = `${width}%`; });
      }
    });
  };
  window.switchTab = window.switchSkillTab;

  /* Botão voltar ao topo: tratado pelo listener global de scroll acima. */
  backTop?.addEventListener("click", () => {
    window.scrollTo({ top: 0, behavior: reduceMotion ? "auto" : "smooth" });
  });

  /* Toasts. */
  window.showToast = function showToast(message, type = "info", duration = 3500) {
    let container = document.getElementById("toast-container");
    if (!container) {
      container = document.createElement("div");
      container.id = "toast-container";
      body.appendChild(container);
    }
    const safeType = ["info", "success", "warning", "error"].includes(type) ? type : "info";
    const toast = document.createElement("div");
    toast.className = `toast ${safeType}`;
    toast.textContent = String(message ?? "");
    container.appendChild(toast);
    window.setTimeout(() => {
      toast.style.opacity = "0";
      toast.style.transform = "translateY(8px)";
      toast.style.transition = "opacity .3s, transform .3s";
      window.setTimeout(() => toast.remove(), 300);
    }, Math.max(0, Number(duration) || 3500));
  };

  /* Lazy images: mantém compatibilidade com elementos que usam data-src. */
  const lazyImages = Array.from(document.querySelectorAll('img[loading="lazy"][data-src]'));
  observeOrShow(lazyImages, { rootMargin: "100px" }, (img) => {
    if (img.dataset.src) {
      img.src = img.dataset.src;
      img.removeAttribute("data-src");
    }
  });

  /* Tema dark/light, com tratamento para armazenamento bloqueado. */
  (function initThemeToggle() {
    const themeButton = document.getElementById("themeToggle");
    const icon = document.getElementById("themeIcon");
    let saved = "dark";
    try {
      saved = localStorage.getItem("jm_theme") || "dark";
    } catch (_) { /* armazenamento pode estar bloqueado */ }
    if (!['dark', 'light'].includes(saved)) saved = "dark";

    function applyTheme(theme, notify = false) {
      root.dataset.theme = theme;
      if (icon) icon.className = theme === "dark" ? "fas fa-moon" : "fas fa-sun";
      themeButton?.setAttribute("aria-label", theme === "dark" ? "Activar tema claro" : "Activar tema escuro");
      try { localStorage.setItem("jm_theme", theme); } catch (_) { /* sem persistência */ }
      if (notify && window.showToast) {
        window.showToast(`Tema ${theme === "dark" ? "escuro" : "claro"} activado`, "info", 2000);
      }
    }

    applyTheme(saved);
    themeButton?.addEventListener("click", () => {
      applyTheme(root.dataset.theme === "dark" ? "light" : "dark", true);
    });
  })();

  /* Debounce global. */
  window.debounce = function debounce(fn, wait = 200) {
    let timer;
    return (...args) => {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => fn(...args), Math.max(0, Number(wait) || 0));
    };
  };
})();