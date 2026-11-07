// ============================================
// PWA NATIVE FEATURES - OTIMIZADO  assets/js/pwa-native.js
// ============================================

class PWANativeManager {
  constructor() {
    this.isStandalone = window.matchMedia("(display-mode: standalone)").matches;
    this.isIOS =
      /iPad|iPhone|iPod/.test(navigator.userAgent) ||
      (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);
    this.isAndroid = /Android/.test(navigator.userAgent);
    this.isPWA = Boolean(window.navigator.standalone || this.isStandalone);
    this.hasHaptic = typeof navigator.vibrate === "function";
    this.hasShare = typeof navigator.share === "function";
    this.hasClipboard = Boolean(
      navigator.clipboard &&
      typeof navigator.clipboard.writeText === "function",
    );
    this.hasBadge = typeof navigator.setAppBadge === "function";

    this.touchStart = { x: 0, y: 0, time: 0 };
    this.swipeThreshold = 100;
    this.swipeTimeThreshold = 500;

    this.init();
  }

  async init() {
    if (!this.isPWA) {
      console.log("Executando como site web normal");
      return;
    }

    console.log("Executando como app nativo PWA");

    // 1. Adicionar classes CSS
    this.addNativeClasses();

    // 2. Splash screen (opcional)
    this.showSplashScreen();

    // 3. Configurar comportamento nativo
    this.setupNativeBehavior();

    // 4. Habilitar features nativas
    this.enableNativeFeatures();

    // 5. Expor API global
    this.exposeAPI();

    console.log("PWA Native Manager inicializado");
  }

  addNativeClasses() {
    document.documentElement.classList.add("pwa-native", "pwa-launched");

    // Adicionar classe específica da plataforma
    if (this.isIOS) {
      document.documentElement.classList.add("pwa-ios");
    } else if (this.isAndroid) {
      document.documentElement.classList.add("pwa-android");
    }

    // Adicionar safe area para iOS
    if (this.isIOS) {
      this.addSafeAreaStyles();
    }
  }

  addSafeAreaStyles() {
    const style = document.createElement("style");
    style.textContent = `
      .pwa-ios {
        padding-top: env(safe-area-inset-top);
        padding-bottom: env(safe-area-inset-bottom);
        padding-left: env(safe-area-inset-left);
        padding-right: env(safe-area-inset-right);
      }
      
      .pwa-ios .header {
        padding-top: env(safe-area-inset-top);
      }
      
      .pwa-ios .pwa-install-btn,
      .pwa-ios .theme-toggle,
      .pwa-ios .back-to-top {
        margin-bottom: env(safe-area-inset-bottom);
      }
    `;
    document.head.appendChild(style);
  }

  showSplashScreen() {
    // Criar splash screen minimalista
    const splash = document.createElement("div");
    splash.className = "pwa-splash";

    splash.innerHTML = `
      <div class="splash-content">
        <div class="splash-logo">
          <i class="fas fa-code"></i>
        </div>
        <div class="splash-text">
          <h2>JMbenga</h2>
          <p>Portfólio Dev</p>
        </div>
      </div>
    `;

    // Adicionar estilos inline apenas para garantir
    splash.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: #0f172a;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 99999;
      opacity: 1;
      transition: opacity 0.5s ease;
    `;

    // Estilos do conteúdo
    const content = splash.querySelector(".splash-content");
    content.style.cssText = `
      text-align: center;
      animation: splashFadeIn 0.5s ease;
    `;

    const logo = splash.querySelector(".splash-logo i");
    logo.style.cssText = `
      font-size: 3rem;
      color: var(--accent);
      margin-bottom: 1rem;
      animation: splashPulse 1.5s ease infinite;
    `;

    const text = splash.querySelector(".splash-text");
    text.style.cssText = `
      color: #f8fafc;
    `;

    document.body.appendChild(splash);

    // Adicionar animações CSS
    if (!document.querySelector("#splash-animations")) {
      const style = document.createElement("style");
      style.id = "splash-animations";
      style.textContent = `
        @keyframes splashFadeIn {
          from { opacity: 0; transform: scale(0.9); }
          to { opacity: 1; transform: scale(1); }
        }
        
        @keyframes splashPulse {
          0%, 100% { transform: scale(1); opacity: 1; }
          50% { transform: scale(1.1); opacity: 0.8; }
        }
      `;
      document.head.appendChild(style);
    }

    // Remover após 1.5 segundos (mais rápido)
    setTimeout(() => {
      splash.style.opacity = "0";
      setTimeout(() => {
        if (splash.parentNode) {
          splash.remove();
        }
      }, 500);
    }, 1500);
  }

  setupNativeBehavior() {
    // 1. Prevenir zoom duplo-tap em iOS PWA
    if (this.isIOS) {
      let lastTouchEnd = 0;

      document.addEventListener(
        "touchend",
        (event) => {
          const now = Date.now();
          if (now - lastTouchEnd <= 300) {
            event.preventDefault();
          }
          lastTouchEnd = now;
        },
        { passive: false },
      );
    }

    // 2. Swipe para voltar (iOS-style) - VERSÃO OTIMIZADA
    this.setupSwipeBack();

    // 3. Prevenir bounce/overscroll em iOS
    if (this.isIOS) {
      document.addEventListener(
        "touchmove",
        (e) => {
          if (e.scale !== 1) {
            e.preventDefault();
          }
        },
        { passive: false },
      );
    }

    // 4. Status bar color (Android)
    if (this.isAndroid) {
      this.setAndroidStatusBar();
    }

    // 5. Detectar mudanças de orientação
    window.addEventListener("orientationchange", () => {
      setTimeout(() => this.handleOrientationChange(), 100);
    });
  }

  setupSwipeBack() {
    // Swipe gesture otimizado - NÃO BLOQUEIA SCROLL
    let touchStartX = 0;
    let touchStartY = 0;
    let touchStartTime = 0;

    document.addEventListener(
      "touchstart",
      (e) => {
        // Só considerar toque próximo à borda esquerda (como iOS)
        if (e.touches[0].clientX < 50) {
          touchStartX = e.touches[0].pageX;
          touchStartY = e.touches[0].pageY;
          touchStartTime = Date.now();
        }
      },
      { passive: true },
    );

    document.addEventListener(
      "touchend",
      (e) => {
        if (touchStartX === 0) return;

        const touchEndX = e.changedTouches[0].pageX;
        const touchEndY = e.changedTouches[0].pageY;
        const elapsedTime = Date.now() - touchStartTime;

        const deltaX = touchEndX - touchStartX;
        const deltaY = Math.abs(touchEndY - touchStartY);

        // Swipe direito (para voltar) - apenas se:
        // 1. Movimento horizontal > 50px
        // 2. Movimento vertical < 100px (não é scroll)
        // 3. Tempo < 500ms (gesto rápido)
        if (deltaX > 50 && deltaY < 100 && elapsedTime < 500) {
          // Visual feedback
          this.vibrate(50);

          // Navegar para trás
          if (window.history.length > 1) {
            window.history.back();
          }
        }

        // Reset
        touchStartX = 0;
        touchStartY = 0;
        touchStartTime = 0;
      },
      { passive: true },
    );
  }

  setAndroidStatusBar() {
    // Para Android, podemos tentar ajustar a status bar
    // Isso é feito via meta tag viewport e theme-color
    const metaThemeColor = document.querySelector('meta[name="theme-color"]');
    if (metaThemeColor) {
      metaThemeColor.content = "#0f172a"; // Cor da sua navbar
    }
  }

  handleOrientationChange() {
    // Ajustar layout para orientação
    const isPortrait = window.innerHeight > window.innerWidth;

    if (isPortrait) {
      document.documentElement.classList.remove("landscape");
      document.documentElement.classList.add("portrait");
    } else {
      document.documentElement.classList.remove("portrait");
      document.documentElement.classList.add("landscape");
    }

    // Mostrar toast informativo
    this.showToast(`Modo ${isPortrait ? "retrato" : "paisagem"}`, "info");
  }

  enableNativeFeatures() {
    // 1. Haptic feedback (vibração)
    this.setupHapticFeedback();

    // 2. Share API
    this.setupShareButton();

    // 3. Clipboard API
    this.setupClipboard();

    // 4. Badge API (notificações)
    this.setupBadge();

    // 5. Network status
    this.setupNetworkStatus();

    // 6. Fullscreen (Android)
    this.setupFullscreen();
  }

  setupHapticFeedback() {
    if (!this.hasHaptic) return;

    // Adicionar feedback tátil em interações
    const elements = document.querySelectorAll(
      "button, .btn, .nav-link, .tech-item",
    );

    elements.forEach((element) => {
      element.addEventListener("click", () => {
        // Vibração leve para feedback tátil
        this.vibrate(30);
      });
    });
  }

  vibrate(duration = 50) {
    if (this.hasHaptic) {
      try {
        navigator.vibrate(duration);
      } catch (e) {
        // Silently fail
      }
    }
  }

  setupShareButton() {
    if (!this.hasShare) return;

    // Adicionar botão de compartilhar se não existir
    if (!document.querySelector("#share-button")) {
      const shareBtn = document.createElement("button");
      shareBtn.id = "share-button";
      shareBtn.className = "native-share-btn";
      shareBtn.innerHTML = '<i class="fas fa-share-alt"></i>';
      shareBtn.setAttribute("aria-label", "Compartilhar portfólio");

      shareBtn.style.cssText = `
        position: fixed;
        bottom: 160px;
        right: 20px;
        width: 56px;
        height: 56px;
        background: linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 78%, #000));
        color: white;
        border: none;
        border-radius: 50%;
        font-size: 1.25rem;
        cursor: pointer;
        z-index: 999;
        box-shadow: 0 4px 20px var(--accent-glow);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
      `;

      shareBtn.addEventListener("mouseenter", () => {
        shareBtn.style.transform = "scale(1.1)";
      });

      shareBtn.addEventListener("mouseleave", () => {
        shareBtn.style.transform = "scale(1)";
      });

      shareBtn.addEventListener("click", async () => {
        try {
          await navigator.share({
            title: "JMbenga - Portfólio Dev",
            text: "Conheça o portfólio de José Mbenga, desenvolvedor Full Stack",
            url: window.location.href,
          });
          this.showToast("Compartilhado com sucesso!", "success");
        } catch (error) {
          if (error.name !== "AbortError") {
            console.log("Compartilhamento cancelado ou não suportado");
          }
        }
      });

      // Apenas mostrar em PWA
      if (this.isPWA) {
        document.body.appendChild(shareBtn);
      }
    }
  }

  setupClipboard() {
    if (!this.hasClipboard) return;

    // Nunca interceptar links mailto: o clique deve abrir o cliente de email.
    // A cópia é activada apenas em elementos que declarem explicitamente o destino.
    const copyElements = document.querySelectorAll(
      "[data-copy-email], [data-copy-to-clipboard]",
    );

    copyElements.forEach((element) => {
      if (element.dataset.clipboardBound === "true") return;
      element.dataset.clipboardBound = "true";

      element.addEventListener("click", async (event) => {
        const value =
          element.dataset.copyEmail || element.dataset.copyToClipboard || "";
        if (!value) return;

        event.preventDefault();
        try {
          await navigator.clipboard.writeText(value);
          this.showToast("Copiado para a área de transferência.", "success");
        } catch (_) {
          this.showToast(
            "Não foi possível copiar. Copie o texto manualmente.",
            "warning",
          );
        }
      });
    });
  }

  setupBadge() {
    if (!this.hasBadge) return;

    // Exemplo: Mostrar badge quando há novas mensagens
    // Você pode adaptar para notificações reais
    window.setAppBadge = (count) => navigator.setAppBadge(count);
    window.clearAppBadge = () =>
      typeof navigator.clearAppBadge === "function"
        ? navigator.clearAppBadge()
        : Promise.resolve(false);

    // Exemplo: Verificar mensagens não lidas (simulado)
    this.checkUnreadMessages();
  }

  async checkUnreadMessages() {
    // Simular verificação de mensagens
    // Na prática, você faria uma chamada API
    const unreadCount = 0; // Mude para >0 para testar

    if (unreadCount > 0 && this.hasBadge) {
      try {
        await navigator.setAppBadge(unreadCount);
      } catch (error) {
        console.log("Não foi possível definir badge:", error);
      }
    }
  }

  setupNetworkStatus() {
    // navigator.onLine é apenas um indício do estado da interface de rede.
    // A confirmação é feita através do endpoint PHP leve do próprio site.
    let lastKnownState = null;
    let activeProbe = null;

    const probeServer = async () => {
      if (activeProbe) return activeProbe;

      activeProbe = (async () => {
        const controller = new AbortController();
        const timeoutId = window.setTimeout(() => controller.abort(), 5000);

        try {
          const configuredBase = String(
            document.documentElement.dataset.baseUrl || window.location.origin,
          ).replace(/\/+$/, "");
          const probeUrl = new URL(`${configuredBase}/manifest.php`, window.location.href);
          probeUrl.searchParams.set("__pwa_probe", String(Date.now()));

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
          window.clearTimeout(timeoutId);
        }
      })().finally(() => {
        activeProbe = null;
      });

      return activeProbe;
    };

    const refreshNetworkState = async (announceChange = false) => {
      const serverReachable = await probeServer();
      const nextState = serverReachable ? "online" : "unavailable";
      const previousState = lastKnownState;
      lastKnownState = nextState;

      document.documentElement.dataset.networkState = nextState;

      // Não apresentar toast no primeiro teste; apenas em transições confirmadas.
      if (!announceChange || previousState === null || previousState === nextState) {
        return serverReachable;
      }

      if (serverReachable) {
        this.showToast("Ligação ao JMbenga restabelecida.", "success");
        this.vibrate(100);
      } else {
        this.showToast(
          "Não foi possível contactar o JMbenga. A Internet ou o servidor pode estar temporariamente indisponível.",
          "warning",
          5000,
        );
        this.vibrate([100, 50, 100]);
      }

      return serverReachable;
    };

    // O evento apenas desencadeia uma verificação; não define o estado sozinho.
    window.addEventListener("online", () => {
      window.setTimeout(() => refreshNetworkState(true), 500);
    });

    window.addEventListener("offline", () => {
      window.setTimeout(() => refreshNetworkState(true), 500);
    });

    // Ao regressar à aplicação, volta a confirmar a disponibilidade do servidor.
    document.addEventListener("visibilitychange", () => {
      if (!document.hidden) refreshNetworkState(false);
    });

    refreshNetworkState(false);
  }

  setupFullscreen() {
    // Para Android PWA, podemos pedir fullscreen
    if (this.isAndroid && document.documentElement.requestFullscreen) {
      // Opcional: pedir fullscreen em certas interações
      const fullscreenTriggers = document.querySelectorAll("[data-fullscreen]");

      fullscreenTriggers.forEach((trigger) => {
        trigger.addEventListener("click", () => {
          document.documentElement.requestFullscreen().catch(() => {
            // Ignorar erro
          });
        });
      });
    }
  }

  // ========== NOTIFICATION SYSTEM ==========

  showToast(message, type = "info", duration = 3000) {
    // O conteúdo recebido é sempre texto, nunca HTML arbitrário.
    const existing = document.querySelector(".native-toast");
    if (existing) {
      existing.classList.add("hiding");
      setTimeout(() => existing.remove(), 300);
    }

    const toast = document.createElement("div");
    toast.className = `native-toast ${type}`;

    const icons = {
      success: "fas fa-check-circle",
      error: "fas fa-exclamation-circle",
      warning: "fas fa-exclamation-triangle",
      info: "fas fa-info-circle",
    };

    const icon = document.createElement("i");
    icon.className = icons[type] || icons.info;
    icon.setAttribute("aria-hidden", "true");

    const text = document.createElement("span");
    text.textContent = String(message ?? "");

    toast.append(icon, text);
    toast.style.bottom = this.isIOS
      ? "calc(80px + env(safe-area-inset-bottom))"
      : "80px";

    document.body.appendChild(toast);

    if (duration === 0) return;
    const safeDuration = Number.isFinite(Number(duration))
      ? Math.max(1000, Number(duration))
      : 3000;

    setTimeout(() => {
      toast.classList.add("hiding");
      setTimeout(() => {
        if (toast.parentNode) toast.remove();
      }, 300);
    }, safeDuration);
  }

  // ========== API GLOBAL ==========

  exposeAPI() {
    window.PWANative = {
      // Getters
      isPWA: this.isPWA,
      isIOS: this.isIOS,
      isAndroid: this.isAndroid,

      // Methods
      showToast: (msg, type, duration) => this.showToast(msg, type, duration),
      vibrate: (pattern) => this.vibrate(pattern),
      share: async (data) => {
        if (this.hasShare) {
          return await navigator.share(data);
        }
        return Promise.reject("Share API not supported");
      },
      copyToClipboard: async (text) => {
        if (this.hasClipboard) {
          await navigator.clipboard.writeText(text);
          this.showToast("Copiado para a área de transferência!", "success");
          return true;
        }
        return false;
      },

      // Badge
      setBadge: async (count) => {
        if (this.hasBadge) {
          return await navigator.setAppBadge(count);
        }
        return false;
      },
      clearBadge: async () => {
        if (this.hasBadge) {
          return await navigator.clearAppBadge();
        }
        return false;
      },

      // Utils
      getPlatformInfo: () => ({
        platform: this.isIOS ? "iOS" : this.isAndroid ? "Android" : "Desktop",
        standalone: this.isStandalone,
        supportsHaptic: this.hasHaptic,
        supportsShare: this.hasShare,
        supportsClipboard: this.hasClipboard,
        supportsBadge: this.hasBadge,
      }),
    };

    console.log("PWANative API exposta globalmente");
  }

  // ========== CLEANUP ==========

  destroy() {
    // Limpar event listeners se necessário
    // (não é crítico para PWAs de página única)
    console.log("🧹 PWANative Manager cleanup");
  }
}

// ========== INITIALIZATION ==========

// Inicializar após o DOM carregar
document.addEventListener("DOMContentLoaded", () => {
  // Aguardar um pouco para não interferir no carregamento inicial
  setTimeout(() => {
    window.pwaNativeManager = new PWANativeManager();
  }, 500);
});

// ========== CSS PARA FEATURES NATIVAS ==========

// Injetar estilos CSS para features nativas
const nativeStyles = `
  .pwa-native {
    /* Estilos para modo app nativo */
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
  }
  
  .pwa-native button,
  .pwa-native a {
    cursor: pointer;
  }
  
  .pwa-splash {
    /* Definido via JS inline */
  }
  
  .native-toast {
    position: fixed;
    left: 50%;
    transform: translateX(-50%);
    background: var(--accent);
    color: white;
    padding: 12px 24px;
    border-radius: 50px;
    z-index: 10001;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
    font-weight: 500;
    max-width: 90%;
    opacity: 0;
    animation: toastIn 0.3s ease forwards;
  }
  
  .native-toast.success {
    background: #10b981;
  }
  
  .native-toast.error {
    background: #ef4444;
  }
  
  .native-toast.warning {
    background: #f59e0b;
  }
  
  .native-toast.info {
    background: var(--accent);
  }
  
  .native-toast.hiding {
    animation: toastOut 0.3s ease forwards;
  }
  
  .native-share-btn {
    /* Definido via JS inline */
  }
  
  @keyframes toastIn {
    from {
      opacity: 0;
      transform: translate(-50%, 20px);
    }
    to {
      opacity: 1;
      transform: translate(-50%, 0);
    }
  }
  
  @keyframes toastOut {
    from {
      opacity: 1;
      transform: translate(-50%, 0);
    }
    to {
      opacity: 0;
      transform: translate(-50%, 20px);
    }
  }
  
  /* iOS specific adjustments */
  .pwa-ios .header {
    height: calc(60px + env(safe-area-inset-top));
    padding-top: env(safe-area-inset-top);
  }
  
  .pwa-ios .offcanvas {
    padding-top: env(safe-area-inset-top);
  }
  
  /* Landscape adjustments */
  .landscape .hero-grid {
    flex-direction: row !important;
  }
  
  .landscape .floating-tech-stack {
    justify-content: center;
  }
`;

// Injetar estilos
if (!document.querySelector("#pwa-native-styles")) {
  const style = document.createElement("style");
  style.id = "pwa-native-styles";
  style.textContent = nativeStyles;
  document.head.appendChild(style);
}

console.log("PWA Native Features carregado!");
