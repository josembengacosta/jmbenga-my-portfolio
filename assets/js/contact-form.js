/**
 * JMbenga Portfolio — contact-form.js
 * Gere o formulário de contacto via fetch → api/contact.php
 * Validação no cliente, feedback visual, anti-spam honeypot.
 * Suporte a meta tag CSRF + campo hidden (fallback).
 */

'use strict';

(function ContactForm() {
  const form   = document.getElementById('contact-form');
  if (!form) return;

  const BASE_URL = document.documentElement.dataset.baseUrl || '';
  const submit   = form.querySelector('[type="submit"]');
  const status   = document.getElementById('form-status');

  // ── Obter token CSRF (meta tag → campo hidden) ──────────────
  function getCSRFToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.getAttribute('content')) {
      return meta.getAttribute('content');
    }
    const hidden = form.querySelector('input[name="csrf_token"]');
    return hidden ? hidden.value : '';
  }

  // ── Validação básica ────────────────────────────────────────
  function validate() {
    let ok = true;
    form.querySelectorAll('[required]').forEach(field => {
      const empty = !field.value.trim();
      field.classList.toggle('error', empty);
      if (empty) ok = false;
    });

    const email = form.querySelector('[name="email"]');
    if (email && email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
      email.classList.add('error');
      ok = false;
    }

    // Validar também o select de assunto (se existir)
    const subjectSelect = form.querySelector('#subject-select');
    if (subjectSelect && subjectSelect.value === '__other__') {
      const customSubject = form.querySelector('#custom-subject');
      if (customSubject && !customSubject.value.trim()) {
        customSubject.classList.add('error');
        ok = false;
      } else if (customSubject) {
        customSubject.classList.remove('error');
      }
    }

    return ok;
  }

  // Limpar erro ao digitar
  form.querySelectorAll('.form-control, .form-select, #custom-subject').forEach(field => {
    field.addEventListener('input', () => field.classList.remove('error'));
    field.addEventListener('change', () => field.classList.remove('error'));
  });

  // ── Submissão ───────────────────────────────────────────────
  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    if (!validate()) {
      showStatus('error', 'Por favor, preencha todos os campos obrigatórios correctamente.');
      return;
    }

    // Honeypot anti-spam
    const honey = form.querySelector('[name="_hp"]');
    if (honey && honey.value) return; // bot detectado

    setLoading(true);
    hideStatus();

    try {
      // Criar FormData e garantir que o token CSRF está presente
      const formData = new FormData(form);
      if (!formData.get('csrf_token')) {
        formData.set('csrf_token', getCSRFToken());
      }

      const res = await fetch(BASE_URL + '/api/contact', {
        method: 'POST',
        body: formData,
      });

      // Respostas não-JSON (ex: erro de servidor)
      const text = await res.text();
      let data;
      try { data = JSON.parse(text); }
      catch { throw new Error('Erro interno do servidor. Tente mais tarde.'); }

      if (!res.ok || !data.success) {
        throw new Error(data.message || 'Não foi possível enviar a mensagem.');
      }

      showStatus('success', '✓ Mensagem enviada com sucesso! Responderei em breve.');
      form.reset();

      // Resetar select de assunto personalizado (se existir)
      const subjectSelect = form.querySelector('#subject-select');
      const customWrap = form.querySelector('#custom-subject-wrap');
      if (subjectSelect) subjectSelect.selectedIndex = 0;
      if (customWrap) customWrap.classList.remove('show');

      if (window.showToast) showToast('Mensagem enviada!', 'success');

    } catch (err) {
      showStatus('error', '✗ ' + err.message);
    } finally {
      setLoading(false);
    }
  });

  // ── Helpers ────────────────────────────────────────────────
  function setLoading(on) {
    submit.disabled = on;
    const icon = submit.querySelector('i');
    const span = submit.querySelector('span');
    if (on) {
      if (icon) { icon.className = 'fas fa-spinner fa-spin'; }
      if (span) span.textContent = 'A enviar...';
    } else {
      if (icon) { icon.className = 'fas fa-paper-plane'; }
      if (span) span.textContent = 'Enviar Mensagem';
    }
  }

  function showStatus(type, msg) {
    if (!status) return;
    status.className = 'form-status ' + type;
    status.textContent = msg;
    status.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function hideStatus() {
    if (!status) return;
    status.className = 'form-status';
    status.textContent = '';
  }
})();