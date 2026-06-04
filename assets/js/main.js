/**
 * JMbenga Portfolio — main.js
 * Inicializado em todas as páginas (via includes/footer.php).
 * Cursor, navbar, reveal, back-to-top, toasts, contadores, skills.
 */

"use strict";

/* ══════════════════════════════════════════════════════════════
   CURSOR PERSONALIZADO
══════════════════════════════════════════════════════════════ */
(function initCursor() {
  const cur = document.getElementById("cursor");
  const ring = document.getElementById("cursor-ring");
  if (!cur || !ring) return;
  if (window.matchMedia("(pointer: coarse)").matches) {
    cur.style.display = ring.style.display = "none";
    return;
  }

  let mx = 0,
    my = 0,
    rx = 0,
    ry = 0;
  document.addEventListener("mousemove", (e) => {
    mx = e.clientX;
    my = e.clientY;
  });

  (function tick() {
    rx += (mx - rx) * 0.12;
    ry += (my - ry) * 0.12;
    cur.style.cssText = `left:${mx - 6}px;top:${my - 6}px`;
    ring.style.cssText = `left:${rx - 18}px;top:${ry - 18}px`;
    requestAnimationFrame(tick);
  })();

  const hoverable = "a, button, input, textarea, select, [data-hover]";
  document.addEventListener("mouseover", (e) => {
    if (e.target.closest(hoverable)) {
      cur.style.transform = "scale(2.2)";
      ring.style.transform = "scale(1.6)";
    }
  });
  document.addEventListener("mouseout", (e) => {
    if (e.target.closest(hoverable)) {
      cur.style.transform = "";
      ring.style.transform = "";
    }
  });
})();

/* ══════════════════════════════════════════════════════════════
   NAVBAR — scroll effect + active link
══════════════════════════════════════════════════════════════ */
(function initNavbar() {
  const nav = document.getElementById("navbar");
  if (!nav) return;

  // Scroll class
  const onScroll = () => nav.classList.toggle("scrolled", window.scrollY > 40);
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  // Active link por hash
  const links = nav.querySelectorAll('.nav-links a[href*="#"]');
  const sections = [];
  links.forEach((l) => {
    const id = l.getAttribute("href").split("#")[1];
    const sec = document.getElementById(id);
    if (sec) sections.push({ link: l, section: sec });
  });

  if (sections.length) {
    const obs = new IntersectionObserver(
      (entries) => {
        entries.forEach((e) => {
          const match = sections.find((s) => s.section === e.target);
          if (match) match.link.classList.toggle("active", e.isIntersecting);
        });
      },
      { threshold: 0.4 }
    );
    sections.forEach((s) => obs.observe(s.section));
  }
})();

/* ══════════════════════════════════════════════════════════════
   MENU MOBILE
══════════════════════════════════════════════════════════════ */
(function initMobileMenu() {
  const toggle = document.querySelector(".nav-toggle");
  const links = document.querySelector(".nav-links");
  if (!toggle || !links) return;

  toggle.addEventListener("click", () => {
    const open = links.classList.toggle("open");
    toggle.classList.toggle("open", open);
    document.body.style.overflow = open ? "hidden" : "";
  });

  links.querySelectorAll("a").forEach((a) => {
    a.addEventListener("click", () => {
      links.classList.remove("open");
      toggle.classList.remove("open");
      document.body.style.overflow = "";
    });
  });
})();

/* ══════════════════════════════════════════════════════════════
   REVEAL ON SCROLL
══════════════════════════════════════════════════════════════ */
(function initReveal() {
  const els = document.querySelectorAll(".reveal");
  if (!els.length) return;

  const obs = new IntersectionObserver(
    (entries) => {
      entries.forEach((e) => {
        if (e.isIntersecting) {
          e.target.classList.add("visible");
        }
      });
    },
    { threshold: 0.08, rootMargin: "0px 0px -40px 0px" }
  );

  els.forEach((el) => obs.observe(el));
})();

/* ══════════════════════════════════════════════════════════════
   CONTADORES NUMÉRICOS
══════════════════════════════════════════════════════════════ */
(function initCounters() {
  const els = document.querySelectorAll(".counter");
  if (!els.length) return;

  const obs = new IntersectionObserver(
    (entries) => {
      entries.forEach((e) => {
        if (!e.isIntersecting) return;
        const el = e.target;
        const end = parseInt(el.dataset.target, 10);
        let cur = 0;
        const step = Math.max(1, Math.ceil(end / 60));
        const tick = setInterval(() => {
          cur = Math.min(cur + step, end);
          el.textContent = cur.toLocaleString("pt-AO");
          if (cur >= end) clearInterval(tick);
        }, 22);
        obs.unobserve(el);
      });
    },
    { threshold: 0.5 }
  );

  els.forEach((el) => obs.observe(el));
})();

/* ══════════════════════════════════════════════════════════════
   BARRAS DE SKILL
══════════════════════════════════════════════════════════════ */
(function initSkillBars() {
  const bars = document.querySelectorAll(".skill-bar-fill");
  if (!bars.length) return;

  const obs = new IntersectionObserver(
    (entries) => {
      entries.forEach((e) => {
        if (e.isIntersecting) {
          e.target.style.width = (e.target.dataset.width || 0) + "%";
          obs.unobserve(e.target);
        }
      });
    },
    { threshold: 0.3 }
  );

  bars.forEach((b) => obs.observe(b));
})();

/* ══════════════════════════════════════════════════════════════
   TABS DE SKILLS
══════════════════════════════════════════════════════════════ */
window.switchSkillTab = function (cat) {
  document
    .querySelectorAll(".skills-tab")
    .forEach((t) => t.classList.toggle("active", t.dataset.tab === cat));
  document
    .querySelectorAll(".skills-panel")
    .forEach((p) => p.classList.toggle("active", p.id === "tab-" + cat));
  // Animar barras do painel recém-activado
  document.querySelectorAll("#tab-" + cat + " .skill-bar-fill").forEach((b) => {
    b.style.width = "0";
    requestAnimationFrame(() => {
      b.style.width = (b.dataset.width || 0) + "%";
    });
  });
};

/* ══════════════════════════════════════════════════════════════
   BOTÃO VOLTAR AO TOPO
══════════════════════════════════════════════════════════════ */
(function initBackTop() {
  const btn = document.getElementById("back-top");
  if (!btn) return;

  window.addEventListener(
    "scroll",
    () => {
      btn.classList.toggle("visible", window.scrollY > 400);
    },
    { passive: true }
  );

  btn.addEventListener("click", () =>
    window.scrollTo({ top: 0, behavior: "smooth" })
  );
})();

/* ══════════════════════════════════════════════════════════════
   TOASTS
══════════════════════════════════════════════════════════════ */
window.showToast = function (msg, type = "info", duration = 3500) {
  let container = document.getElementById("toast-container");
  if (!container) {
    container = document.createElement("div");
    container.id = "toast-container";
    document.body.appendChild(container);
  }

  const toast = document.createElement("div");
  toast.className = `toast ${type}`;
  toast.textContent = msg;
  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = "0";
    toast.style.transform = "translateY(8px)";
    toast.style.transition = "all .3s";
    setTimeout(() => toast.remove(), 300);
  }, duration);
};

/* ══════════════════════════════════════════════════════════════
   LAZY IMAGES
══════════════════════════════════════════════════════════════ */
(function initLazyImages() {
  if (!("IntersectionObserver" in window)) return;

  const imgs = document.querySelectorAll('img[loading="lazy"]');
  const obs = new IntersectionObserver((entries) => {
    entries.forEach((e) => {
      if (e.isIntersecting) {
        const img = e.target;
        if (img.dataset.src) {
          img.src = img.dataset.src;
          img.removeAttribute("data-src");
        }
        obs.unobserve(img);
      }
    });
  });
  imgs.forEach((img) => obs.observe(img));
})();

// ═══ THEME TOGGLE ═════════════════════════════════════════
(function initThemeToggle() {
  const toggle = document.getElementById("themeToggle");
  const icon = document.getElementById("themeIcon");
  const html = document.documentElement;

  function applyTheme(theme, showNotification = false) {
    html.dataset.theme = theme;
    if (icon) icon.className = theme === "dark" ? "fas fa-moon" : "fas fa-sun";
    localStorage.setItem("jm_theme", theme);
    if (showNotification && window.showToast) {
      showToast(`Tema ${theme === "dark" ? "escuro" : "claro"} ativado`, "info", 2000);
    }
  }

  // Aplicar tema guardado ou padrão dark (sem notificação)
  const saved = localStorage.getItem("jm_theme") || "dark";
  applyTheme(saved, false);

  if (toggle) {
    toggle.addEventListener("click", () => {
      const current = html.dataset.theme;
      const newTheme = current === "dark" ? "light" : "dark";
      applyTheme(newTheme, true); // Mostra notificação ao alternar
    });
  }
})();

// ═══ OFF CANVAS ═══════════════════════════════════════════
(function initOffcanvas() {
  const toggle = document.querySelector(".nav-toggle");
  const offcanvas = document.getElementById("offcanvas");
  const backdrop = document.getElementById("backdrop");

  if (!toggle || !offcanvas) return;

  function openMenu() {
    offcanvas.classList.add("open");
    backdrop.classList.add("open");
    toggle.classList.add("open");
    document.body.style.overflow = "hidden";
  }

  window.closeMenu = function () {
    offcanvas.classList.remove("open");
    backdrop.classList.remove("open");
    toggle.classList.remove("open");
    document.body.style.overflow = "";
  };

  toggle.addEventListener("click", openMenu);
  backdrop.addEventListener("click", closeMenu);
  offcanvas.querySelectorAll("a").forEach((a) => {
    a.addEventListener("click", closeMenu);
  });
})();

/* ══════════════════════════════════════════════════════════════
   UTILITÁRIO: debounce
══════════════════════════════════════════════════════════════ */
window.debounce = function (fn, wait = 200) {
  let t;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn(...args), wait);
  };
};
