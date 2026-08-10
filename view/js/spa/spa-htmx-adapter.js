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
    isSPARoute,
    auxiliarySelectors,
    scrollToTopOnNavigate,
    scrollToTopInstant,
    cleanupTooltips,
    reinitializeDynamicContent,
    showTimeoutModal
  } = options;

  const selectorsToSync = Array.isArray(auxiliarySelectors) && auxiliarySelectors.length > 0
    ? auxiliarySelectors
    : ['nav#topbar-first', 'div#topbar-second', 'aside', 'right_aside'];
  let pendingNavigationUrl = null;

  function dispatchBeforeNavigate(url) {
    const path = new URL(url, window.location.href).pathname;
    const beforeEvent = new CustomEvent('spa:beforeNavigate', {
      detail: { path: path, url: url }
    });
    window.dispatchEvent(beforeEvent);
  }

  function isInternalLink(link) {
    const href = link.getAttribute('href');
    if (!href) return false;
    if (href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:')) return false;
    try {
      const url = new URL(href, window.location.href);
      return url.origin === window.location.origin;
    } catch (e) {
      return false;
    }
  }

  function normalizeLinkUrl(link) {
    const href = link.getAttribute('href');
    if (!href) {
      return null;
    }

    try {
      let normalizedHref = href;
      if (!normalizedHref.startsWith('http://') && !normalizedHref.startsWith('https://') && !normalizedHref.startsWith('/')) {
        normalizedHref = '/' + normalizedHref;
      }
      return new URL(normalizedHref, window.location.href).toString();
    } catch (e) {
      return null;
    }
  }

  function configureBoostTarget() {
    const content = document.getElementById('content');
    if (!content) {
      console.warn('[SPA HTMX] Missing #content container; HTMX adapter disabled');
      return false;
    }

    return true;
  }

  function syncAuxiliaryRegions(xhr) {
    const responseText = xhr?.responseText;
    if (!responseText || typeof responseText !== 'string') {
      return;
    }

    const parser = new DOMParser();
    const incomingDoc = parser.parseFromString(responseText, 'text/html');

    selectorsToSync.forEach(selector => {
      const incoming = incomingDoc.querySelector(selector);
      const current = document.querySelector(selector);
      if (incoming && current) {
        current.innerHTML = incoming.innerHTML;
      }
    });

    const incomingTitle = incomingDoc.querySelector('title');
    if (incomingTitle) {
      document.title = incomingTitle.textContent;
    }
  }

  function handleDocumentClick(event) {
    const link = event.target.closest('a');
    if (!link) {
      return;
    }

    const href = link.getAttribute('href');
    if (!href) {
      return;
    }

    if (href.startsWith('#')) {
      return;
    }

    if (link.classList.contains('modal-open')) {
      return;
    }

    if (link.hasAttribute('data-fancybox')) {
      history.pushState({ __fancyboxMarker: true }, '', window.location.href);
      return;
    }

    if (link.hasAttribute('onclick') || link.onclick || link.hasAttribute('data-spa-ignore')) {
      return;
    }

    if (link.hasAttribute('target') && link.getAttribute('target') !== '_self') {
      return;
    }

    if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
      return;
    }

    if (!isInternalLink(link)) {
      return;
    }

    const url = normalizeLinkUrl(link);
    if (!url) {
      return;
    }

    const path = new URL(url, window.location.href).pathname;
    if (!isSPARoute(path)) {
      event.preventDefault();
      window.location.href = url;
      return;
    }

    event.preventDefault();
    pendingNavigationUrl = url;
    dispatchBeforeNavigate(url);
    if (typeof showFetching === 'function') {
      showFetching();
    }

    if (typeof cleanupTooltips === 'function') {
      cleanupTooltips();
    }

    try {
      window.htmx.ajax('GET', url, {
        target: '#content',
        swap: 'outerHTML',
        select: '#content',
        pushURL: true,
        source: link
      });
    } catch (e) {
      console.warn('[SPA HTMX] ajax navigation failed, falling back to full reload', e);
      window.location.href = url;
    }
  }

  function wireEvents() {
    document.addEventListener('click', handleDocumentClick);

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

      if (typeof cleanupTooltips === 'function') {
        cleanupTooltips();
      }

      syncAuxiliaryRegions(event?.detail?.xhr);

      const effectiveUrl = pendingNavigationUrl || window.location.href;
      const effectivePath = new URL(effectiveUrl, window.location.href).pathname;
      if (scrollToTopOnNavigate && !effectivePath.includes('/display/') && typeof scrollToTopInstant === 'function') {
        scrollToTopInstant();
      }
      pendingNavigationUrl = null;

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