/**
 * JMbenga Portfolio — contact-form.js
 * Envia o formulário para /api/contact, valida os campos no cliente,
 * sincroniza assunto/pacote e mantém as protecções CSRF/honeypot.
 */
'use strict';

(function ContactForm() {
  const form = document.getElementById('contact-form');
  if (!form) return;

  const submit = form.querySelector('[type="submit"]');
  const status = document.getElementById('form-status');
  const subjectSelect = form.querySelector('#subject-select');
  const customWrap = form.querySelector('#custom-subject-wrap');
  const customInput = form.querySelector('#custom-subject');
  const hiddenSubject = form.querySelector('#subject-hidden');
  const packageWrap = form.querySelector('#package-wrap');
  const packageSelect = form.querySelector('#package-select');
  const messageInput = form.querySelector('[name="message"]');

  let submitting = false;
  const originalButton = submit ? submit.innerHTML : '';
  let lastAutoMessage = messageInput && messageInput.dataset.packageAutofilled === 'true'
    ? messageInput.value.trim()
    : '';
  let messageManuallyEdited = false;

  if (status) {
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
  }

  function getCSRFToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.content.trim()) return meta.content.trim();

    const hidden = form.querySelector('input[name="csrf_token"]');
    return hidden ? hidden.value.trim() : '';
  }

  // Usa a base URL definida pelo layout. No XAMPP, infere o subdirectório
  // quando o layout não publica data-base-url.
  function getBaseURL() {
    const configured = (document.documentElement.dataset.baseUrl || '').trim();
    if (configured) return configured.replace(/\/+$/, '');

    const path = window.location.pathname.replace(/\/+$/, '');
    const match = path.match(/^(.*)\/(?:contact(?:\.php)?|index\.php)$/i);
    return match ? match[1].replace(/\/+$/, '') : '';
  }

  function getEndpoint() {
    const configuredEndpoint = (form.dataset.endpoint || '').trim();
    if (configuredEndpoint) return new URL(configuredEndpoint, window.location.href).toString();
    return `${getBaseURL()}/api/contact`;
  }

  function packageMessage(packageName) {
    return `Olá, tenho interesse no pacote ${packageName} de Desenvolvimento Web. Gostaria de solicitar um orçamento.`;
  }

  function syncPackage() {
    const isWeb = !!subjectSelect && subjectSelect.value === 'Desenvolvimento Web';
    if (packageWrap) packageWrap.style.display = isWeb ? 'block' : 'none';

    if (!isWeb && packageSelect && packageSelect.value !== '') {
      packageSelect.value = '';
    }

    if (!messageInput || !packageSelect) return;

    const currentMessage = messageInput.value.trim();
    const canUpdateMessage = !messageManuallyEdited && (currentMessage === ''
      || (lastAutoMessage !== '' && currentMessage === lastAutoMessage));

    if (canUpdateMessage) {
      if (isWeb && packageSelect.value !== '') {
        messageInput.value = packageMessage(packageSelect.value);
        lastAutoMessage = messageInput.value.trim();
        messageInput.dataset.packageAutofilled = 'true';
      } else if (lastAutoMessage !== '' && currentMessage === lastAutoMessage) {
        messageInput.value = '';
        lastAutoMessage = '';
        messageInput.dataset.packageAutofilled = 'false';
      }
    }
  }

  // Mantém o assunto visual e o campo enviado à API sincronizados.
  function syncSubject() {
    if (!subjectSelect || !hiddenSubject) return;

    const isOther = subjectSelect.value === '__other__';
    if (customWrap) {
      customWrap.classList.toggle('show', isOther);
      customWrap.style.display = isOther ? 'block' : 'none';
    }

    if (customInput) {
      customInput.required = isOther;
      if (!isOther) customInput.classList.remove('error');
    }

    hiddenSubject.value = isOther
      ? (customInput ? customInput.value.trim() : '')
      : subjectSelect.value;

    syncPackage();
  }

  if (subjectSelect) subjectSelect.addEventListener('change', syncSubject);
  if (packageSelect) packageSelect.addEventListener('change', syncPackage);

  if (customInput) {
    customInput.addEventListener('input', () => {
      if (hiddenSubject && subjectSelect && subjectSelect.value === '__other__') {
        hiddenSubject.value = customInput.value.trim();
      }
      customInput.classList.remove('error');
    });
  }

  if (messageInput) {
    messageInput.addEventListener('input', () => {
      // Se o visitante editar o texto sugerido, não o substituir automaticamente.
      if (messageInput.value.trim() !== lastAutoMessage) {
        messageManuallyEdited = true;
        messageInput.dataset.packageAutofilled = 'false';
      }
    });
  }

  syncSubject();

  function validate() {
    let valid = true;

    form.querySelectorAll('[required]').forEach((field) => {
      const value = typeof field.value === 'string' ? field.value.trim() : '';
      const empty = value === '';
      field.classList.toggle('error', empty);
      if (empty) valid = false;
    });

    const email = form.querySelector('[name="email"]');
    if (email && email.value.trim() !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
      email.classList.add('error');
      valid = false;
    }

    if (subjectSelect) {
      const missingSubject = subjectSelect.value === '';
      subjectSelect.classList.toggle('error', missingSubject);
      if (missingSubject) valid = false;

      if (subjectSelect.value === '__other__' && (!customInput || !customInput.value.trim())) {
        if (customInput) customInput.classList.add('error');
        valid = false;
      }
    }

    syncSubject();
    return valid;
  }

  form.querySelectorAll('.form-control, .form-select, #custom-subject').forEach((field) => {
    field.addEventListener('input', () => field.classList.remove('error'));
    field.addEventListener('change', () => field.classList.remove('error'));
  });

  function setLoading(on) {
    if (submit) {
      submit.disabled = on;
      submit.setAttribute('aria-busy', on ? 'true' : 'false');
      if (on) {
        submit.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i><span>A enviar...</span>';
      } else {
        submit.innerHTML = originalButton;
      }
    }
    submitting = on;
  }

  function showStatus(type, message) {
    if (!status) return;
    status.className = `form-status ${type}`;
    status.textContent = message;
    status.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function hideStatus() {
    if (!status) return;
    status.className = 'form-status';
    status.textContent = '';
  }

  function resetFieldsAfterSuccess() {
    form.reset();
    if (subjectSelect) subjectSelect.value = '';
    if (packageSelect) packageSelect.value = '';
    if (customInput) {
      customInput.value = '';
      customInput.required = false;
      customInput.classList.remove('error');
    }
    if (hiddenSubject) hiddenSubject.value = '';
    if (messageInput) {
      messageInput.value = '';
      messageInput.dataset.packageAutofilled = 'false';
    }
    lastAutoMessage = '';
    messageManuallyEdited = false;
    if (customWrap) {
      customWrap.classList.remove('show');
      customWrap.style.display = 'none';
    }
    if (packageWrap) packageWrap.style.display = 'none';
    form.querySelectorAll('.error').forEach((field) => field.classList.remove('error'));
  }

  form.addEventListener('reset', () => {
    window.setTimeout(syncSubject, 0);
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (submitting) return;

    syncSubject();
    if (!validate()) {
      showStatus('error', 'Por favor, preencha todos os campos obrigatórios correctamente.');
      return;
    }

    const honeypot = form.querySelector('[name="_hp"]');
    if (honeypot && honeypot.value.trim() !== '') return;

    hideStatus();
    setLoading(true);

    try {
      const formData = new FormData(form);
      const csrfToken = getCSRFToken();
      if (!formData.get('csrf_token') && csrfToken) formData.set('csrf_token', csrfToken);

      if (hiddenSubject) formData.set('subject', hiddenSubject.value.trim());
      if (packageSelect && subjectSelect && subjectSelect.value === 'Desenvolvimento Web') {
        formData.set('package', packageSelect.value.trim());
      } else {
        formData.set('package', '');
      }

      const response = await fetch(getEndpoint(), {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });

      const responseText = await response.text();
      let data;
      try {
        data = JSON.parse(responseText);
      } catch (_parseError) {
        throw new Error('O servidor devolveu uma resposta inesperada. Tente novamente mais tarde.');
      }

      if (!response.ok || !data || !data.success) {
        throw new Error((data && typeof data.message === 'string' && data.message.trim())
          ? data.message
          : 'Não foi possível enviar a mensagem. Tente novamente.');
      }

      showStatus('success', data.message || 'Mensagem enviada com sucesso! Obrigado pelo contacto.');
      resetFieldsAfterSuccess();

      if (typeof window.showToast === 'function') window.showToast('Mensagem enviada!', 'success');
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Ocorreu um erro inesperado.';
      showStatus('error', message);
    } finally {
      setLoading(false);
    }
  });
})();
