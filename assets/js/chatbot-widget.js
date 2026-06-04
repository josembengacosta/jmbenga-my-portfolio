"use strict";

(function initJmChatbot() {
  const widget = document.getElementById("jm-chatbot");
  if (!widget) return;

  const panel = document.getElementById("jm-chatbot-panel");
  const toggle = document.getElementById("jm-chatbot-toggle");
  const closeBtn = document.getElementById("jm-chatbot-close");
  const form = document.getElementById("jm-chatbot-form");
  const input = document.getElementById("jm-chatbot-input");
  const send = document.getElementById("jm-chatbot-send");
  const messages = document.getElementById("jm-chatbot-messages");
  const baseUrl = document.documentElement.dataset.baseUrl || "";
  const apiUrl = widget.dataset.apiUrl || `${baseUrl}/api/chat-api.php`;
  const MAX_INPUT = 600;

  // ── Persistência via sessionStorage ─────────────────────────
  const STORAGE_KEY = "jm_chatbot_v2";
  let history = [];
  let messageEls = []; // {role, text, el}

  function loadSession() {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY);
      if (!raw) return;
      const data = JSON.parse(raw);
      if (Array.isArray(data.history)) history = data.history;
      if (Array.isArray(data.messages)) {
        data.messages.forEach((m) => renderMessage(m.role, m.text, false));
      }
    } catch (e) {
      // Ignorar corrupção
    }
  }

  function saveSession() {
    try {
      const payload = {
        history,
        messages: messageEls.map((m) => ({ role: m.role, text: m.text })),
        ts: Date.now(),
      };
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
    } catch (e) {
      // sessionStorage pode estar cheio
    }
  }

  function clearSession() {
    history = [];
    messageEls = [];
    messages.innerHTML = "";
    sessionStorage.removeItem(STORAGE_KEY);
  }

  // ── Acessibilidade: focus trap ─────────────────────────────
  const focusableSelector =
    'a[href], button, textarea, input, select, [tabindex]:not([tabindex="-1"])';

  function getFocusable(container) {
    return Array.from(container.querySelectorAll(focusableSelector)).filter(
      (el) => el.offsetParent !== null && !el.disabled
    );
  }

  function trapFocus(e) {
    if (!widget.classList.contains("is-open")) return;
    const focusable = getFocusable(panel);
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (e.key === "Tab") {
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  }

  // ── UI helpers ──────────────────────────────────────────────
  function setOpen(open) {
    widget.classList.toggle("is-open", open);
    panel.setAttribute("aria-hidden", String(!open));
    toggle.setAttribute("aria-expanded", String(open));
    toggle.setAttribute("aria-label", open ? "Fechar chat" : "Abrir chat");
    if (open) {
      setTimeout(() => input.focus(), 160);
      document.addEventListener("keydown", trapFocus);
    } else {
      document.removeEventListener("keydown", trapFocus);
    }
  }

  function scrollToEnd() {
    messages.scrollTop = messages.scrollHeight;
  }

  function resizeInput() {
    input.style.height = "auto";
    input.style.height = `${Math.min(input.scrollHeight, 112)}px`;
  }

  function updateCounter() {
    const len = input.value.length;
    let counter = document.getElementById("jm-chatbot-counter");
    if (!counter) {
      counter = document.createElement("div");
      counter.id = "jm-chatbot-counter";
      counter.style.cssText =
        "font-size:11px;color:#94a3b8;text-align:right;margin-top:2px;";
      input.parentNode.appendChild(counter);
    }
    counter.textContent = `${len}/${MAX_INPUT}`;
    counter.style.color = len > MAX_INPUT * 0.9 ? "#ef4444" : "#94a3b8";
  }

  function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  function linkify(text) {
    // Converter URLs em links, mas manter texto seguro
    const urlPattern = /(https?:\/\/[^\s]+)/g;
    return text
      .split(urlPattern)
      .map((part, i) => {
        if (i % 2 === 1) {
          const safe = escapeHtml(part);
          return `<a href="${safe}" target="_blank" rel="noopener noreferrer" style="color:#2563eb;text-decoration:underline;">${safe}</a>`;
        }
        return escapeHtml(part);
      })
      .join("");
  }

  function renderMessage(role, text, animate = true) {
    const bubble = document.createElement("div");
    bubble.className = `jm-chatbot-message ${role}`;
    bubble.setAttribute("role", "listitem");

    if (role === "error") {
      bubble.textContent = text; // textContent = seguro
    } else {
      // linkify converte URLs mas escapa o resto
      bubble.innerHTML = linkify(text);
    }

    messages.appendChild(bubble);
    messageEls.push({ role, text, el: bubble });
    scrollToEnd();

    if (animate) {
      bubble.style.opacity = "0";
      bubble.style.transform = "translateY(8px)";
      requestAnimationFrame(() => {
        bubble.style.transition = "opacity .25s, transform .25s";
        bubble.style.opacity = "1";
        bubble.style.transform = "translateY(0)";
      });
    }

    return bubble;
  }

  function addTyping() {
    const bubble = document.createElement("div");
    bubble.className = "jm-chatbot-message bot typing";
    bubble.setAttribute("role", "status");
    bubble.setAttribute("aria-live", "polite");
    bubble.setAttribute("aria-label", "A pensar...");
    bubble.innerHTML = "<span></span><span></span><span></span>";
    messages.appendChild(bubble);
    scrollToEnd();
    return bubble;
  }

  function setSending(active) {
    sending = active;
    send.disabled = active;
    input.disabled = active;
    widget.classList.toggle("is-sending", active);
  }

  // ── Submissão ───────────────────────────────────────────────
  let sending = false;

  async function submitMessage(event) {
    event.preventDefault();
    if (sending) return;

    const text = input.value.trim();
    if (!text) return;
    if (text.length > MAX_INPUT) {
      renderMessage("error", `Mensagem muito longa. Máximo ${MAX_INPUT} caracteres.`);
      return;
    }

    const apiHistory = history.slice(-10);
    renderMessage("user", text);
    input.value = "";
    resizeInput();
    updateCounter();
    setSending(true);

    const typing = addTyping();
    const t0 = performance.now();

    try {
      const response = await fetch(apiUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
        },
        body: JSON.stringify({
          message: text,
          history: apiHistory,
        }),
      });

      const data = await response.json().catch(() => ({
        error: "Resposta inválida do servidor.",
      }));

      typing.remove();

      if (!response.ok || !data.response) {
        const errText = data.error || "Não foi possível obter resposta.";
        renderMessage("error", errText);
        return;
      }

      renderMessage("bot", data.response);
      history.push({ role: "user", text }, { role: "model", text: data.response });

      if (history.length > 20) {
        history.splice(0, history.length - 20);
      }

      saveSession();
    } catch (error) {
      typing.remove();
      renderMessage("error", "Erro de rede. Tente novamente.");
    } finally {
      setSending(false);
      input.focus();
    }
  }

  // ── Event listeners ─────────────────────────────────────────
  toggle.addEventListener("click", () => {
    setOpen(!widget.classList.contains("is-open"));
  });

  closeBtn.addEventListener("click", () => setOpen(false));
  form.addEventListener("submit", submitMessage);
  input.addEventListener("input", () => {
    resizeInput();
    updateCounter();
  });

  input.addEventListener("keydown", (event) => {
    if (event.key === "Enter" && !event.shiftKey) {
      event.preventDefault();
      if (typeof form.requestSubmit === "function") {
        form.requestSubmit();
      } else {
        send.click();
      }
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && widget.classList.contains("is-open")) {
      setOpen(false);
    }
  });

  // ── Inicialização ─────────────────────────────────────────
  // Configurar aria-live no container de mensagens
  messages.setAttribute("role", "log");
  messages.setAttribute("aria-live", "polite");
  messages.setAttribute("aria-relevant", "additions");
  messages.setAttribute("aria-atomic", "false");

  loadSession();
  resizeInput();
  updateCounter();

  // Botão de limpar conversa (opcional, se existir no HTML)
  const clearBtn = document.getElementById("jm-chatbot-clear");
  if (clearBtn) {
    clearBtn.addEventListener("click", () => {
      if (confirm("Limpar toda a conversa?")) {
        clearSession();
      }
    });
  }
})();