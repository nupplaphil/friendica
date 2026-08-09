// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

// @license magnet:?xt=urn:btih:0b31508aeb0634b347b8270c7bee4d411b5d4109&dn=agpl-3.0.txt AGPLv3-or-later

function isHtmxEnabled() {
  if (window.spaUseHtmx === true) {
    return true;
  }

  const params = new URLSearchParams(window.location.search);
  return params.get('spa_htmx') === '1';
}

function ensureHtmxLoaded() {
  if (window.htmx) {
    return Promise.resolve(true);
  }

  const existing = document.querySelector('script[data-spa-htmx-loader="1"]');
  if (existing) {
    return new Promise(resolve => {
      existing.addEventListener('load', () => resolve(!!window.htmx), { once: true });
      existing.addEventListener('error', () => resolve(false), { once: true });
    });
  }

  const script = document.createElement('script');
  script.setAttribute('data-spa-htmx-loader', '1');
  script.src = window.spaHtmxScriptSrc || '/view/asset/htmx.org/dist/htmx.min.js';
  script.defer = true;

  return new Promise(resolve => {
    script.onload = () => resolve(!!window.htmx);
    script.onerror = () => resolve(false);
    document.head.appendChild(script);
  });
}

function createHtmxAdapter(options) {
  const {
    reinitializeDynamicContent,
    showTimeoutModal
  } = options;

  function dispatchBeforeNavigate(url) {
    const path = new URL(url, window.location.href).pathname;
    const beforeEvent = new CustomEvent('spa:beforeNavigate', {
      detail: { path: path, url: url }
    });
    window.dispatchEvent(beforeEvent);
  }

  function configureBoostTarget() {
    const content = document.getElementById('content');
    if (!content) {
      console.warn('[SPA HTMX] Missing #content container; HTMX adapter disabled');
      return false;
    }

    content.setAttribute('hx-boost', 'true');
    content.setAttribute('hx-target', '#content');
    content.setAttribute('hx-select', '#content');
    content.setAttribute('hx-swap', 'innerHTML');
    content.setAttribute('hx-push-url', 'true');

    if (window.htmx && typeof window.htmx.process === 'function') {
      window.htmx.process(content);
    }

    return true;
  }

  function wireEvents() {
    document.body.addEventListener('htmx:beforeRequest', (event) => {
      const requestPath = event?.detail?.pathInfo?.requestPath || window.location.href;
      dispatchBeforeNavigate(requestPath);
      if (typeof showFetching === 'function') {
        showFetching();
      }
    });

    document.body.addEventListener('htmx:beforeSwap', () => {
      if (typeof showProcessing === 'function') {
        showProcessing();
      }
    });

    document.body.addEventListener('htmx:responseError', (event) => {
      const xhr = event?.detail?.xhr;
      if (xhr && xhr.status === 401) {
        window.location.href = '/login?return_path=' + encodeURIComponent(window.location.href);
        return;
      }
      if (xhr && xhr.status === 504) {
        if (typeof hideLoading === 'function') {
          hideLoading();
        }
        showTimeoutModal();
      }
    });

    document.body.addEventListener('htmx:afterSwap', (event) => {
      const target = event?.detail?.target;
      if (!target || target.id !== 'content') {
        return;
      }

      if (typeof hideLoading === 'function') {
        hideLoading();
      }

      window.__spa_reinit_phase = true;
      reinitializeDynamicContent(() => {});
      window.__spa_reinit_phase = false;
    });
  }

  async function init() {
    const loaded = await ensureHtmxLoaded();
    if (!loaded || !window.htmx) {
      console.warn('[SPA HTMX] HTMX could not be loaded; falling back to router implementation');
      return false;
    }

    const configured = configureBoostTarget();
    if (!configured) {
      return false;
    }

    wireEvents();
    console.debug('[SPA HTMX] HTMX SPA adapter initialized');
    return true;
  }

  return {
    init
  };
}

export {
  isHtmxEnabled,
  createHtmxAdapter,
  ensureHtmxLoaded
};