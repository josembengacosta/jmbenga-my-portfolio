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

  // Se o HTML estiver incompleto, não inicializar parcialmente o chatbot.
  if (!panel || !toggle || !closeBtn || !form || !input || !send || !messages) return;

  const baseUrl = document.documentElement.dataset.baseUrl || "";
  const configuredApiUrl = widget.dataset.apiUrl || `${baseUrl}/api/core-sync`;
  const apiUrl = configuredApiUrl.replace(/\/$/, "");

  const MAX_INPUT = 600;
  const MAX_HISTORY_ITEMS = 20;
  const MAX_STORED_MESSAGES = 60;
  const REQUEST_TIMEOUT_MS = 70000;
  const SESSION_TTL_MS = 30 * 60 * 1000;
  const STORAGE_KEY = "jm_chatbot_v2";

  let history = [];
  let messageEls = []; // { role, text, el }
  let sending = false;
  let requestTimer = null;
  let activeController = null;
  // Incrementado ao limpar para ignorar respostas de pedidos cancelados.
  let activeRequestId = 0;

  // ── Persistência via sessionStorage ─────────────────────────
  function safeSessionGet() {
    try {
      return sessionStorage.getItem(STORAGE_KEY);
    } catch (_) {
      return null;
    }
  }

  function safeSessionSet(value) {
    try {
      sessionStorage.setItem(STORAGE_KEY, value);
      return true;
    } catch (_) {
      return false;
    }
  }

  function safeSessionRemove() {
    try {
      sessionStorage.removeItem(STORAGE_KEY);
    } catch (_) {
      // O armazenamento pode estar bloqueado pelo navegador.
    }
  }

  function normalizeHistoryItem(item) {
    if (!item || typeof item !== "object") return null;

    const roleValue = String(item.role || "").toLowerCase();
    const role = ["model", "assistant", "bot", "gemini"].includes(roleValue)
      ? "model"
      : roleValue === "user"
        ? "user"
        : null;
    const text = typeof item.text === "string"
      ? item.text.trim()
      : typeof item.content === "string"
        ? item.content.trim()
        : "";

    if (!role || !text) return null;
    return { role, text: text.slice(0, 2000) };
  }

  function loadSession() {
    const raw = safeSessionGet();
    if (!raw) return;

    try {
      const data = JSON.parse(raw);
      if (!data || typeof data !== "object") throw new Error("Formato inválido");

      const timestamp = Number(data.ts || 0);
      if (!timestamp || Date.now() - timestamp > SESSION_TTL_MS || timestamp > Date.now() + 60000) {
        safeSessionRemove();
        return;
      }

      history = Array.isArray(data.history)
        ? data.history.map(normalizeHistoryItem).filter(Boolean).slice(-MAX_HISTORY_ITEMS)
        : [];

      if (Array.isArray(data.messages)) {
        data.messages
          .filter((item) => item && typeof item.text === "string")
          .slice(-MAX_STORED_MESSAGES)
          .forEach((item) => {
            const role = ["user", "bot", "error"].includes(item.role) ? item.role : null;
            if (role && item.text.trim()) renderMessage(role, item.text.slice(0, 4000), false);
          });
      }
    } catch (_) {
      history = [];
      messageEls = [];
      messages.replaceChildren();
      safeSessionRemove();
    }
  }

  function saveSession() {
    const payload = {
      history: history.slice(-MAX_HISTORY_ITEMS),
      messages: messageEls.slice(-MAX_STORED_MESSAGES).map(({ role, text }) => ({ role, text })),
      ts: Date.now(),
    };
    safeSessionSet(JSON.stringify(payload));
  }

  function normalizeCommandText(value) {
    return String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase()
      .replace(/[.,!?;:]+/g, " ")
      .replace(/\s+/g, " ")
      .trim();
  }

  function isClearConversationCommand(value) {
    const command = normalizeCommandText(value);
    // Aceita comandos directos e pequenas variantes/erros comuns como "impa".
    const startsAsClear = /^(?:(?:por favor|podes|pode|quero|preciso(?: de)?|gostaria de)\s+)*(?:l?impa(?:r)?|apaga(?:r)?|elimina(?:r)?|remove(?:r)?)\b/.test(command);
    const hasConversationTarget = /\b(?:conversa(?:s)?|chat|historico|mensagens|tudo)\b/.test(command);
    return startsAsClear && hasConversationTarget;
  }

  function clearSession() {
    // Invalida a resposta pendente antes de abortar o pedido em curso.
    activeRequestId += 1;
    if (requestTimer !== null) {
      window.clearTimeout(requestTimer);
      requestTimer = null;
    }
    if (activeController) {
      try { activeController.abort(); } catch (_) { /* ignorar */ }
    }
    activeController = null;
    history = [];
    messageEls = [];
    safeSessionRemove();
    messages.replaceChildren();

    // Aviso local: não é enviado ao Gemini nem guardado como histórico.
    const notice = document.createElement("div");
    notice.className = "jm-chatbot-message bot";
    notice.setAttribute("role", "listitem");
    notice.style.whiteSpace = "pre-wrap";
    notice.textContent = "A conversa foi limpa. Podemos começar de novo. Em que posso ajudar?";
    messages.appendChild(notice);
    scrollToEnd();
    setSending(false);
  }

  // ── Acessibilidade: focus trap ─────────────────────────────
  const focusableSelector =
    'a[href], button, textarea, input, select, [tabindex]:not([tabindex="-1"])';

  function getFocusable(container) {
    return Array.from(container.querySelectorAll(focusableSelector)).filter(
      (el) => el.offsetParent !== null && !el.disabled
    );
  }

  function trapFocus(event) {
    if (!widget.classList.contains("is-open")) return;
    const focusable = getFocusable(panel);
    if (!focusable.length) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.key === "Tab") {
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
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
      window.setTimeout(() => input.focus(), 160);
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
    div.textContent = String(text);
    return div.innerHTML;
  }

  function linkify(text) {
    const urlPattern = /(https?:\/\/[^\s<]+)/g;
    return String(text)
      .split(urlPattern)
      .map((part, index) => {
        if (index % 2 !== 1) return escapeHtml(part);

        // Retirar pontuação final comum sem alterar a URL apresentada no texto.
        const match = part.match(/[.,!?;:)\]]+$/);
        const trailing = match ? match[0] : "";
        const rawUrl = trailing ? part.slice(0, -trailing.length) : part;
        if (!/^https?:\/\//i.test(rawUrl)) return escapeHtml(part);

        const safeUrl = escapeHtml(rawUrl);
        return `<a href="${safeUrl}" target="_blank" rel="noopener noreferrer nofollow" style="color:#2563eb;text-decoration:underline;">${safeUrl}</a>${escapeHtml(trailing)}`;
      })
      .join("");
  }

  function trimRenderedMessages() {
    while (messageEls.length > MAX_STORED_MESSAGES) {
      const oldest = messageEls.shift();
      if (oldest && oldest.el && oldest.el.parentNode) oldest.el.remove();
    }
  }

  function renderMessage(role, text, animate = true) {
    const safeRole = ["user", "bot", "error"].includes(role) ? role : "bot";
    const plainText = String(text);
    const bubble = document.createElement("div");
    bubble.className = `jm-chatbot-message ${safeRole}`;
    // Preservar quebras de linha para respostas estruturadas em texto simples.
    bubble.style.whiteSpace = "pre-wrap";
    bubble.style.overflowWrap = "anywhere";
    bubble.setAttribute("role", "listitem");

    if (safeRole === "error") {
      bubble.textContent = plainText;
    } else {
      bubble.innerHTML = linkify(plainText);
    }

    messages.appendChild(bubble);
    messageEls.push({ role: safeRole, text: plainText, el: bubble });
    trimRenderedMessages();
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

  function getServerError(responseData, status) {
    if (responseData && typeof responseData.error === "string" && responseData.error.trim()) {
      return responseData.error.trim().slice(0, 500);
    }
    if (status === 429) return "Enviaste mensagens demasiado depressa. Aguarda um pouco e tenta novamente.";
    if (status >= 500) return "O assistente está temporariamente indisponível. Tenta novamente dentro de instantes.";
    return "Não foi possível processar a mensagem. Verifica o pedido e tenta novamente.";
  }

  // ── Submissão ───────────────────────────────────────────────
  async function submitMessage(event) {
    event.preventDefault();
    if (sending) return;

    const text = input.value.trim();
    if (!text) return;

    if (text.length > MAX_INPUT) {
      renderMessage("error", `Mensagem muito longa. Máximo ${MAX_INPUT} caracteres.`);
      return;
    }

    // Comando local: limpar a conversa sem consumir pedidos da API/Gemini.
    if (isClearConversationCommand(text)) {
      input.value = "";
      resizeInput();
      updateCounter();
      clearSession();
      return;
    }

    const apiHistory = history.slice(-10);
    renderMessage("user", text);
    input.value = "";
    resizeInput();
    updateCounter();
    // Guardar já a mensagem do visitante para que não desapareça se falhar a rede.
    saveSession();
    setSending(true);
    const thisRequestId = ++activeRequestId;

    const typing = addTyping();
    activeController = typeof AbortController !== "undefined" ? new AbortController() : null;
    if (activeController) {
      requestTimer = window.setTimeout(() => activeController.abort(), REQUEST_TIMEOUT_MS);
    }

    try {
      const response = await fetch(apiUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
        },
        credentials: "same-origin",
        body: JSON.stringify({ message: text, history: apiHistory }),
        ...(activeController ? { signal: activeController.signal } : {}),
      });

      if (thisRequestId !== activeRequestId) return;
      const rawResponse = await response.text();
      if (thisRequestId !== activeRequestId) return;
      let data;
      try {
        data = rawResponse ? JSON.parse(rawResponse) : {};
      } catch (_) {
        data = { error: "O servidor devolveu uma resposta inválida." };
      }

      typing.remove();

      if (!response.ok || !data || typeof data.response !== "string" || !data.response.trim()) {
        renderMessage("error", getServerError(data, response.status));
        saveSession();
        return;
      }

      renderMessage("bot", data.response.trim());
      history.push({ role: "user", text }, { role: "model", text: data.response.trim() });
      history = history.map(normalizeHistoryItem).filter(Boolean).slice(-MAX_HISTORY_ITEMS);
      saveSession();
    } catch (error) {
      typing.remove();
      if (thisRequestId !== activeRequestId) return;
      if (error && error.name === "AbortError") {
        renderMessage("error", "O assistente demorou demasiado tempo a responder. Verifica a ligação e tenta novamente.");
      } else if (navigator.onLine === false) {
        renderMessage("error", "Estás sem ligação à Internet. A tua mensagem ficou nesta conversa; tenta enviá-la novamente quando a ligação voltar.");
      } else {
        renderMessage("error", "Não foi possível contactar o assistente. Verifica a ligação ou tenta novamente dentro de instantes.");
      }
      saveSession();
    } finally {
      // Um pedido cancelado pela limpeza não deve alterar o estado novo do chat.
      if (thisRequestId === activeRequestId) {
        if (requestTimer !== null) {
          window.clearTimeout(requestTimer);
          requestTimer = null;
        }
        activeController = null;
        setSending(false);
        // Evitar roubar o foco se o utilizador fechou o chat durante o pedido.
        if (widget.classList.contains("is-open")) input.focus();
      }
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
    if (event.key === "Escape" && widget.classList.contains("is-open")) setOpen(false);
  });

  messages.setAttribute("role", "log");
  messages.setAttribute("aria-live", "polite");
  messages.setAttribute("aria-relevant", "additions");
  messages.setAttribute("aria-atomic", "false");

  loadSession();
  resizeInput();
  updateCounter();

  const clearBtn = document.getElementById("jm-chatbot-clear");
  if (clearBtn) {
    clearBtn.addEventListener("click", () => {
      if (window.confirm("Pretende limpar a conversa actual? O histórico guardado nesta sessão será apagado.")) {
        clearSession();
      }
    });
  }
})();