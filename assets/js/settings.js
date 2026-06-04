/**
 * JMbenga Admin — Settings Pages Common JS
 * Incluir em todas as páginas de settings após o body
 */
(function() {
  'use strict';

  const BASE_URL = document.documentElement.dataset.baseUrl || '';

  // ── Toast System ────────────────────────────────────────────
  window.showToast = function(message, type = 'success', duration = 4000) {
    const container = document.getElementById('toast-container') || (() => {
      const d = document.createElement('div');
      d.id = 'toast-container';
      document.body.appendChild(d);
      return d;
    })();

    const icons = {
      success: 'fa-check-circle',
      error: 'fa-times-circle',
      warning: 'fa-exclamation-circle'
    };

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    toast.innerHTML = `<i class="fas ${icons[type] || icons.success}" aria-hidden="true"></i> <span>${escapeHtml(message)}</span>`;
    container.appendChild(toast);

    requestAnimationFrame(() => {
      setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(20px)';
        setTimeout(() => toast.remove(), 300);
      }, duration);
    });
  };

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // ── Switch Labels ───────────────────────────────────────────
  document.querySelectorAll('.switch input[type="checkbox"]').forEach(chk => {
    const updateLabel = () => {
      const label = chk.closest('.switch')?.querySelector('.switch-label');
      if (label) {
        label.textContent = chk.checked ? (chk.dataset.on || 'Activo') : (chk.dataset.off || 'Inactivo');
      }
    };
    chk.addEventListener('change', updateLabel);
    updateLabel();
  });

  // ── Form AJAX Handler ───────────────────────────────────────
  document.querySelectorAll('form[data-api]').forEach(form => {
    form.addEventListener('submit', async function(e) {
      e.preventDefault();

      const submitBtn = form.querySelector('button[type="submit"]');
      const originalHtml = submitBtn ? submitBtn.innerHTML : '';

      // Validação nativa
      let valid = true;
      form.querySelectorAll('[required]').forEach(field => {
        if (!field.value.trim()) {
          field.classList.add('error');
          valid = false;
        } else {
          field.classList.remove('error');
        }
      });

      if (!valid) {
        showToast('Preenche os campos obrigatórios.', 'error');
        const firstError = form.querySelector('.error');
        if (firstError) firstError.focus();
        return;
      }

      // Loading state
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner"></span> A guardar…';
      }
      form.classList.add('is-loading');

      try {
        const formData = new FormData(form);
        const csrfToken = form.querySelector('[name="csrf_token"]')?.value || '';

        const res = await fetch(form.dataset.api, {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
          },
          credentials: 'same-origin'
        });

        const data = await res.json().catch(() => ({
          success: false,
          message: 'Resposta inválida do servidor.'
        }));

        if (data.success) {
          showToast(data.message || 'Configurações guardadas com sucesso!', 'success');
          // Remover erros visuais
          form.querySelectorAll('.error').forEach(el => el.classList.remove('error'));
        } else {
          if (Array.isArray(data.errors)) {
            data.errors.forEach(err => showToast(err, 'error'));
          } else {
            showToast(data.message || 'Erro ao guardar.', 'error');
          }
        }
      } catch (err) {
        showToast('Erro de rede. Verifica a ligação.', 'error');
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalHtml;
        }
        form.classList.remove('is-loading');
      }
    });
  });

  // ── Real-time validation (remove error on input) ──────────
  document.querySelectorAll('.form-control').forEach(input => {
    input.addEventListener('input', function() {
      this.classList.remove('error');
    });
  });

  // ── Character counters ────────────────────────────────────
  document.querySelectorAll('[data-max-length]').forEach(input => {
    const max = parseInt(input.dataset.maxLength, 10);
    if (!max) return;

    const counter = document.createElement('div');
    counter.className = 'form-counter';
    input.parentNode.appendChild(counter);

    const update = () => {
      const len = input.value.length;
      counter.textContent = `${len}/${max}`;
      counter.classList.toggle('is-limit', len > max * 0.9);
      if (len > max) {
        input.classList.add('error');
      }
    };
    input.addEventListener('input', update);
    update();
  });

  // ── Auto-dismiss alerts ─────────────────────────────────────
  document.querySelectorAll('.alert').forEach(alert => {
    const closeBtn = alert.querySelector('.alert-close');
    if (closeBtn) {
      closeBtn.addEventListener('click', () => alert.remove());
    }
    // Auto-dismiss após 8s para success
    if (alert.classList.contains('alert-success')) {
      setTimeout(() => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-8px)';
        setTimeout(() => alert.remove(), 300);
      }, 8000);
    }
  });

  // ── Confirm before leave with unsaved changes ───────────────
  let formChanged = false;
  document.querySelectorAll('form[data-api]').forEach(form => {
    form.addEventListener('input', () => { formChanged = true; });
    form.addEventListener('submit', () => { formChanged = false; });
  });
  window.addEventListener('beforeunload', (e) => {
    if (formChanged) {
      e.preventDefault();
      e.returnValue = '';
    }
  });
})();