/*
 * Corcovado Foundation — contact and volunteer forms (WordPress).
 *
 * Replaces the mailto: behaviour of the static site's main.js with a secure submission to
 * the site's own endpoint (nonce, signed time token, honeypot, rate limit on the server).
 * The "Copy application" button keeps the original behaviour.
 */
(function () {
  'use strict';

  var cfg = window.CF_FORMS;
  if (!cfg) return;
  var M = cfg.messages || {};
  var isEs = (document.documentElement.lang || '').toLowerCase().indexOf('es') === 0;

  function refreshToken() {
    return fetch(cfg.tokenUrl, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (t) { if (t && t.nonce) { cfg.nonce = t.nonce; cfg.token = t.token; } })
      .catch(function () {});
  }

  function addHoneypot(form) {
    if (form.querySelector('input[name="website"]')) return;
    var wrap = document.createElement('div');
    wrap.setAttribute('aria-hidden', 'true');
    wrap.style.cssText = 'position:absolute!important;left:-10000px!important;top:auto!important;width:1px!important;height:1px!important;overflow:hidden!important;';
    var input = document.createElement('input');
    input.type = 'text';
    input.name = 'website';
    input.tabIndex = -1;
    input.autocomplete = 'off';
    wrap.appendChild(input);
    form.appendChild(wrap);
  }

  function send(endpoint, form, retried) {
    var data = new FormData(form);
    data.append('_cf_nonce', cfg.nonce || '');
    data.append('_cf_token', cfg.token || '');
    data.append('lang', isEs ? 'es' : 'en');
    return fetch(endpoint, { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (body) { return { status: r.status, body: body }; });
      })
      .then(function (res) {
        if (res.body && res.body.ok) return 'ok';
        var code = res.body && res.body.code;
        if (!retried && code === 'invalid_nonce') {
          return refreshToken().then(function () {
            return new Promise(function (resolve) { setTimeout(resolve, 3200); });
          }).then(function () { return send(endpoint, form, true); });
        }
        if (!retried && code === 'too_fast') {
          return new Promise(function (resolve) { setTimeout(resolve, 3200); }).then(function () { return send(endpoint, form, true); });
        }
        if (code === 'rate_limited') return 'rate';
        if (code === 'invalid') return 'required';
        return 'error';
      })
      .catch(function () { return 'error'; });
  }

  function setStatus(el, key) {
    if (el) el.textContent = M[key] || '';
  }

  function prepare(form) {
    addHoneypot(form);
    var once = false;
    form.addEventListener('focusin', function () {
      if (once) return;
      once = true;
      refreshToken();
    });
  }

  // Contact form.
  document.querySelectorAll('[data-contact-form]').forEach(function (form) {
    prepare(form);
    var busy = false;
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (busy) return;
      var status = form.querySelector('[data-contact-status]');
      var name = form.querySelector('[name="name"]');
      var email = form.querySelector('[name="email"]');
      var message = form.querySelector('[name="message"]');
      if (!name || !email || !message || !name.value.trim() || !email.value.trim() || !message.value.trim() || !email.checkValidity()) {
        if (email && typeof email.reportValidity === 'function') email.reportValidity();
        if (!email || email.value.trim()) {
          if (name && !name.value.trim() && typeof name.reportValidity === 'function') name.reportValidity();
          if (message && !message.value.trim() && typeof message.reportValidity === 'function') message.reportValidity();
        }
        setStatus(status, 'required');
        return;
      }
      busy = true;
      setStatus(status, 'sending');
      send(cfg.contactUrl, form, false).then(function (result) {
        busy = false;
        if (result === 'ok') {
          form.reset();
          setStatus(status, 'contactSuccess');
        } else {
          setStatus(status, result);
        }
      });
    });
  });

  // Volunteer application.
  var wrap = document.querySelector('[data-volunteer-form]');
  if (wrap) {
    var form = wrap.querySelector('form');
    var submitBtn = wrap.querySelector('[data-volunteer-submit]');
    var copyBtn = wrap.querySelector('[data-copy-volunteer]');
    var status = wrap.querySelector('[data-volunteer-status]');
    if (form && !status) {
      status = document.createElement('p');
      status.className = 'form-status';
      status.setAttribute('role', 'status');
      status.setAttribute('aria-live', 'polite');
      status.setAttribute('data-volunteer-status', '');
      var actions = form.querySelector('.form-actions');
      if (actions && actions.parentNode) actions.parentNode.insertBefore(status, actions.nextSibling);
      else form.appendChild(status);
    }
    if (form) {
      prepare(form);
      // Pressing Enter in a field submits through the same secure path.
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (submitBtn) submitBtn.click();
      });
    }

    function buildMessage() {
      var get = function (name) { return wrap.querySelector('[name="' + name + '"]'); };
      var val = function (name, fallback) { var el = get(name); return (el && el.value && el.value.trim()) || fallback; };
      var program = val('program', 'Not provided');
      return {
        subject: 'Volunteer application — ' + program,
        body: [
          'Volunteer application from Corcovado Foundation website',
          'Full name: ' + val('full_name', 'Not provided'),
          'Email: ' + val('email', 'Not provided'),
          'Phone: ' + val('phone', 'Not provided'),
          'Country: ' + val('country', 'Not provided'),
          'Program of interest: ' + program,
          'Preferred dates: ' + val('dates', 'Not provided'),
          'Experience: ' + val('experience', 'Not provided'),
          'Availability: ' + val('availability', 'Not provided'),
          '',
          'Message:',
          val('message', 'No additional message.')
        ].join('\n')
      };
    }

    var busy = false;
    if (submitBtn && form) {
      submitBtn.addEventListener('click', function (e) {
        e.preventDefault();
        if (busy) return;
        if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
          setStatus(status, 'required');
          return;
        }
        busy = true;
        setStatus(status, 'sending');
        send(cfg.volunteerUrl, form, false).then(function (result) {
          busy = false;
          if (result === 'ok') {
            form.reset();
            setStatus(status, 'volunteerSuccess');
          } else {
            setStatus(status, result);
          }
        });
      });
    }

    if (copyBtn) {
      copyBtn.addEventListener('click', function () {
        var msg = buildMessage();
        var text = msg.subject + '\n\n' + msg.body;
        var done = function () {
          var copiedLabel = copyBtn.dataset.copiedLabel || 'Copied';
          var label = copyBtn.dataset.copyLabel || 'Copy application';
          copyBtn.textContent = copiedLabel;
          setTimeout(function () { copyBtn.textContent = label; }, 1600);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(done, function () { window.prompt('Copy your application', text); });
        } else {
          window.prompt('Copy your application', text);
        }
      });
    }
  }
})();
