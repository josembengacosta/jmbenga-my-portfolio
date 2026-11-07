/**
 * JMbenga Portfolio — home.js
 * Efeitos exclusivos da página inicial: texto rotativo e partículas.
 * É seguro carregar este ficheiro globalmente: cada efeito só arranca se o seu elemento existir.
 */
"use strict";

(function initTypingAnimation() {
  const element = document.getElementById("typing-word");
  if (!element) return;

  // As palavras são fornecidas pelo PHP a partir das competências visíveis na BD.
  // Não manter uma segunda lista manual aqui: o painel é a fonte de verdade.
  let words = [];
  try {
    const parsedWords = JSON.parse(element.dataset.words || "[]");
    if (Array.isArray(parsedWords)) {
      words = parsedWords
        .filter((word) => typeof word === "string")
        .map((word) => word.trim())
        .filter(Boolean);
    }
  } catch (error) {
    console.warn("Não foi possível ler as competências do texto rotativo.", error);
  }

  if (words.length === 0) {
    element.textContent = "";
    return;
  }

  const reduceMotion = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches ?? false;
  if (reduceMotion) {
    element.textContent = words[0];
    return;
  }

  let wordIndex = 0;
  let charIndex = 0;
  let deleting = false;
  let timer = 0;

  function tick() {
    timer = 0;
    const word = words[wordIndex];
    charIndex += deleting ? -1 : 1;
    element.textContent = word.slice(0, Math.max(0, charIndex));

    if (!deleting && charIndex >= word.length) {
      deleting = true;
      timer = window.setTimeout(tick, 1400);
      return;
    }
    if (deleting && charIndex <= 0) {
      deleting = false;
      wordIndex = (wordIndex + 1) % words.length;
    }
    timer = window.setTimeout(tick, deleting ? 55 : 90);
  }

  tick();
  document.addEventListener("visibilitychange", () => {
    if (document.hidden) {
      window.clearTimeout(timer);
      timer = 0;
    } else if (!timer) {
      tick();
    }
  });
})();

(function initParticles() {
  const canvas = document.getElementById("particles-canvas");
  if (!canvas || typeof canvas.getContext !== "function") return;
  const context = canvas.getContext("2d");
  if (!context) return;

  const reduceMotion = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches ?? false;
  const cssAccent = getComputedStyle(document.documentElement).getPropertyValue("--accent").trim() || "#2563eb";

  function toRgb(value) {
    const hex = value.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
    if (hex) {
      let digits = hex[1];
      if (digits.length === 3) digits = digits.split("").map((char) => char + char).join("");
      const number = Number.parseInt(digits, 16);
      return `${(number >> 16) & 255}, ${(number >> 8) & 255}, ${number & 255}`;
    }
    const rgb = value.match(/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
    if (rgb) return `${rgb[1]}, ${rgb[2]}, ${rgb[3]}`;
    return "37, 99, 235";
  }
  const accentRgb = toRgb(cssAccent);

  let width = 0;
  let height = 0;
  let animationId = 0;
  let particles = [];
  const particleCount = 55;

  function resize() {
    const rect = canvas.getBoundingClientRect();
    const oldWidth = width || rect.width || 1;
    const oldHeight = height || rect.height || 1;
    width = Math.max(1, Math.round(rect.width));
    height = Math.max(1, Math.round(rect.height));
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = Math.round(width * dpr);
    canvas.height = Math.round(height * dpr);
    context.setTransform(dpr, 0, 0, dpr, 0, 0);

    if (particles.length) {
      particles.forEach((particle) => {
        particle.x = (particle.x / oldWidth) * width;
        particle.y = (particle.y / oldHeight) * height;
      });
    } else {
      particles = Array.from({ length: particleCount }, () => ({
        x: Math.random() * width,
        y: Math.random() * height,
        radius: Math.random() * 1.5 + 0.5,
        vx: (Math.random() - 0.5) * 0.35,
        vy: (Math.random() - 0.5) * 0.35,
        alpha: Math.random() * 0.5 + 0.1,
      }));
    }
  }

  function draw() {
    if (document.hidden) {
      animationId = 0;
      return;
    }
    context.clearRect(0, 0, width, height);
    particles.forEach((particle, index) => {
      particle.x += particle.vx;
      particle.y += particle.vy;
      if (particle.x < 0) particle.x = width;
      if (particle.x > width) particle.x = 0;
      if (particle.y < 0) particle.y = height;
      if (particle.y > height) particle.y = 0;

      context.beginPath();
      context.arc(particle.x, particle.y, particle.radius, 0, Math.PI * 2);
      context.fillStyle = `rgba(${accentRgb}, ${particle.alpha})`;
      context.fill();

      for (let next = index + 1; next < particles.length; next++) {
        const other = particles[next];
        const distance = Math.hypot(particle.x - other.x, particle.y - other.y);
        if (distance < 130) {
          context.beginPath();
          context.moveTo(particle.x, particle.y);
          context.lineTo(other.x, other.y);
          context.strokeStyle = `rgba(${accentRgb}, ${(1 - distance / 130) * 0.24})`;
          context.lineWidth = 0.5;
          context.stroke();
        }
      }
    });
    animationId = window.requestAnimationFrame(draw);
  }

  resize();
  window.addEventListener("resize", resize, { passive: true });
  if (!reduceMotion) animationId = window.requestAnimationFrame(draw);

  document.addEventListener("visibilitychange", () => {
    if (document.hidden) {
      if (animationId) window.cancelAnimationFrame(animationId);
      animationId = 0;
    } else if (!reduceMotion && !animationId) {
      animationId = window.requestAnimationFrame(draw);
    }
  });
})();
