"use strict";

/* ── Loading screen ────────────────────────────────────────── */
window.addEventListener("load", () => {
  setTimeout(() => {
    const l = document.getElementById("loading");
    if (l) l.classList.add("hidden");
  }, 900);
});

/* ── Scroll progress bar ───────────────────────────────────── */
const scrollBar = document.getElementById("scroll-progress");
const header = document.getElementById("header");
const backTop = document.getElementById("back-top");

window.addEventListener(
  "scroll",
  () => {
    const scrolled = window.scrollY;
    const total = document.body.scrollHeight - window.innerHeight;
    if (scrollBar) scrollBar.style.width = (scrolled / total) * 100 + "%";
    if (header) header.classList.toggle("scrolled", scrolled > 50);
    if (backTop) backTop.classList.toggle("visible", scrolled > 400);
  },
  {
    passive: true,
  }
);

/* ── Offcanvas ──────────────────────────────────────────────── */
const offcanvas = document.getElementById("offcanvas");
const backdrop = document.getElementById("backdrop");
const toggle = document.getElementById("menuToggle");

function openMenu() {
  offcanvas.classList.add("open");
  backdrop.classList.add("open");
  toggle.classList.add("open");
  document.body.style.overflow = "hidden";
}

function closeMenu() {
  offcanvas.classList.remove("open");
  backdrop.classList.remove("open");
  toggle.classList.remove("open");
  document.body.style.overflow = "";
}

/* ── Back to top ────────────────────────────────────────────── */
if (backTop)
  backTop.addEventListener("click", () =>
    window.scrollTo({
      top: 0,
      behavior: "smooth",
    })
  );

/* ── Active nav link ────────────────────────────────────────── */
const sections = document.querySelectorAll("section[id]");
const navLinks = document.querySelectorAll(".nav-link");

const secObs = new IntersectionObserver(
  (entries) => {
    entries.forEach((e) => {
      if (e.isIntersecting) {
        navLinks.forEach((l) =>
          l.classList.toggle(
            "active",
            l.getAttribute("href") === "#" + e.target.id
          )
        );
      }
    });
  },
  {
    threshold: 0.35,
  }
);
sections.forEach((s) => secObs.observe(s));

/* ── Reveal ─────────────────────────────────────────────────── */
const revObs = new IntersectionObserver(
  (entries) => {
    entries.forEach((e) => {
      if (e.isIntersecting) e.target.classList.add("visible");
    });
  },
  {
    threshold: 0.08,
    rootMargin: "0px 0px -40px 0px",
  }
);
document.querySelectorAll(".reveal").forEach((el) => revObs.observe(el));

/* ── Timeline ───────────────────────────────────────────────── */
const tlObs = new IntersectionObserver(
  (entries) => {
    entries.forEach((e) => {
      if (e.isIntersecting) e.target.classList.add("visible");
    });
  },
  {
    threshold: 0.2,
  }
);
document.querySelectorAll(".timeline-item").forEach((el, i) => {
  el.style.transitionDelay = i * 0.12 + "s";
  tlObs.observe(el);
});

/* ── Contadores ─────────────────────────────────────────────── */
const cntObs = new IntersectionObserver(
  (entries) => {
    entries.forEach((e) => {
      if (!e.isIntersecting) return;
      const el = e.target,
        end = parseInt(el.dataset.target);
      let cur = 0;
      const step = Math.max(1, Math.ceil(end / 60));
      const t = setInterval(() => {
        cur = Math.min(cur + step, end);
        el.textContent = cur;
        if (cur >= end) clearInterval(t);
      }, 22);
      cntObs.unobserve(el);
    });
  },
  {
    threshold: 0.5,
  }
);
document.querySelectorAll(".counter").forEach((el) => cntObs.observe(el));

/* ── Skill bars ─────────────────────────────────────────────── */
const barObs = new IntersectionObserver(
  (entries) => {
    entries.forEach((e) => {
      if (e.isIntersecting) {
        e.target.style.width = e.target.dataset.width + "%";
        barObs.unobserve(e.target);
      }
    });
  },
  {
    threshold: 0.3,
  }
);
document.querySelectorAll(".skill-bar-fill").forEach((b) => barObs.observe(b));

/* ── Skill tabs ─────────────────────────────────────────────── */
function switchTab(cat) {
  document
    .querySelectorAll(".skill-tab")
    .forEach((t) => t.classList.toggle("active", t.dataset.tab === cat));
  document
    .querySelectorAll(".skills-panel")
    .forEach((p) => p.classList.toggle("active", p.id === "tab-" + cat));
  setTimeout(() => {
    document
      .querySelectorAll("#tab-" + cat + " .skill-bar-fill")
      .forEach((b) => (b.style.width = b.dataset.width + "%"));
  }, 50);
}

/* ── Typing animation ───────────────────────────────────────── */
(function () {
  const words = [
    "React.js",
    "Vue.js",
    "Node.js",
    "PHP 8",
    "UI/UX Design",
    "APIs REST",
    "MySQL",
    "TypeScript",
  ];
  const el = document.getElementById("typing-word");
  if (!el) return;
  let wi = 0,
    ci = 0,
    del = false;

  function tick() {
    const w = words[wi];
    el.textContent = del ? w.slice(0, ci--) : w.slice(0, ci++);
    if (!del && ci > w.length) {
      del = true;
      setTimeout(tick, 1400);
      return;
    }
    if (del && ci < 0) {
      del = false;
      wi = (wi + 1) % words.length;
      ci = 0;
    }
    setTimeout(tick, del ? 55 : 90);
  }
  tick();
})();

/* ── Particles canvas ───────────────────────────────────────── */
(function () {
  const canvas = document.getElementById("particles-canvas");
  if (!canvas) return;
  const ctx = canvas.getContext("2d");
  let W,
    H,
    particles = [];
  const N = 55,
    accent =
      getComputedStyle(document.documentElement)
        .getPropertyValue("--accent")
        .trim() || "#2563eb";

  function resize() {
    W = canvas.width = canvas.offsetWidth;
    H = canvas.height = canvas.offsetHeight;
  }
  window.addEventListener("resize", resize);
  resize();

  for (let i = 0; i < N; i++) {
    particles.push({
      x: Math.random() * 1200,
      y: Math.random() * 900,
      r: Math.random() * 1.5 + 0.5,
      vx: (Math.random() - 0.5) * 0.35,
      vy: (Math.random() - 0.5) * 0.35,
      a: Math.random() * 0.5 + 0.1,
    });
  }

  function draw() {
    ctx.clearRect(0, 0, W, H);
    particles.forEach((p, i) => {
      p.x += p.vx;
      p.y += p.vy;
      if (p.x < 0) p.x = W;
      if (p.x > W) p.x = 0;
      if (p.y < 0) p.y = H;
      if (p.y > H) p.y = 0;

      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fillStyle =
        accent +
        Math.floor(p.a * 255)
          .toString(16)
          .padStart(2, "0");
      ctx.fill();

      // Linhas de ligação
      for (let j = i + 1; j < N; j++) {
        const q = particles[j];
        const d = Math.hypot(p.x - q.x, p.y - q.y);
        if (d < 130) {
          ctx.beginPath();
          ctx.moveTo(p.x, p.y);
          ctx.lineTo(q.x, q.y);
          ctx.strokeStyle =
            accent +
            Math.floor((1 - d / 130) * 60)
              .toString(16)
              .padStart(2, "0");
          ctx.lineWidth = 0.5;
          ctx.stroke();
        }
      }
    });
    requestAnimationFrame(draw);
  }
  draw();
})();