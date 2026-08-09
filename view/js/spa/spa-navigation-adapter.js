// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

// @license magnet:?xt=urn:btih:0b31508aeb0634b347b8270c7bee4d411b5d4109&dn=agpl-3.0.txt AGPLv3-or-later

/**
 * Build a navigation adapter that handles link interception and history navigation.
 *
 * @param {Object} options
 * @param {(path: string) => boolean} options.isSPARoute
 * @param {(url: string) => void} options.loadContent
 */
function createNavigationAdapter(options) {
  const { isSPARoute, loadContent } = options;

  function dispatchBeforeNavigate(path, url) {
    const beforeEvent = new CustomEvent('spa:beforeNavigate', {
      detail: { path: path, url: url }
    });
    window.dispatchEvent(beforeEvent);
  }

  /**
   * Check if a link is an internal link (same origin)
   * @param {HTMLAnchorElement} link - The anchor element
   * @returns {boolean} True if internal link
   */
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

  /**
   * Navigate to URL using SPA
   * @param {string} url - The URL to navigate to
   */
  function navigateTo(url) {
    console.debug('[SPA Router] Navigate: url=', url);

    const path = new URL(url, window.location.href).pathname;
    console.debug('[SPA Router] Navigate: parsed path=', path);

    if (!isSPARoute(path)) {
      console.debug('[SPA Router] Navigate: Not an SPA route, falling back to full reload');
      window.location.href = url;
      return;
    }

    console.debug('[SPA Router] Navigate: SPA route detected, pushing state and loading content');

    dispatchBeforeNavigate(path, url);
    history.pushState({ path: path, spa: true, __friendicaSPA: true }, '', url);
    loadContent(url);
  }

  /**
   * Handle link clicks
   * @param {Event} e - Click event
   */
  function handleLinkClick(e) {
    const link = e.target.closest('a');
    if (!link) return;

    let href = link.getAttribute('href');

    // Ignore anchor-only links (e.g., href="#" or href="#back-to-top")
    if (href && href.startsWith('#')) {
      console.debug('[SPA Router] Click: Anchor link, allowing default behavior');
      return;
    }

    // Ignore links that are meant to open modals (e.g., modal-open class)
    if (link.classList.contains('modal-open')) {
      console.debug('[SPA Router] Click: Modal link, allowing default/modal behavior');
      return;
    }

    // Ignore links with data-fancybox attribute (for Fancybox lightbox)
    if (link.hasAttribute('data-fancybox')) {
      console.debug('[SPA Router] Click: Fancybox link, pushing marker state and allowing default/fancybox behavior');
      history.pushState({ __fancyboxMarker: true }, '', window.location.href);
      return;
    }

    // Ignore links with inline event handlers (onclick, etc.)
    if (link.hasAttribute('onclick') || link.onclick || link.hasAttribute('data-spa-ignore')) {
      console.debug('[SPA Router] Click: Link has event handler or data-spa-ignore, allowing default behavior');
      return;
    }

    console.debug('[SPA Router] Click: Found anchor, href=', href);

    if (!isInternalLink(link)) {
      console.debug('[SPA Router] Click: Not an internal link, skipping');
      return;
    }

    if (!href.startsWith('http://') && !href.startsWith('https://') && !href.startsWith('/')) {
      href = '/' + href;
    }

    // Allow middle-click, ctrl-click, cmd-click to open in new tab
    if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey) {
      console.debug('[SPA Router] Click: Modified click (middle/ctrl/cmd/shift), skipping');
      return;
    }

    console.debug('[SPA Router] Click: Preventing default, navigating to', href);
    e.preventDefault();
    navigateTo(href);
  }

  /**
   * Handle browser back/forward navigation
   * @param {Event} e - Popstate event
   */
  function handlePopState(e) {
    console.debug('[SPA Router] PopState: state=', e.state, 'hash=', window.location.hash);

    if (e.state?.spa && e.state.__friendicaSPA) {
      console.debug('[SPA Router] PopState: SPA navigation detected, loading', window.location.href);

      dispatchBeforeNavigate(window.location.pathname, window.location.href);
      loadContent(window.location.href);
    } else {
      console.debug('[SPA Router] PopState: Not an SPA state or not from our router, ignoring');
    }
  }

  function bindNavigationEvents() {
    document.addEventListener('click', handleLinkClick);
    window.addEventListener('popstate', handlePopState);
  }

  return {
    isInternalLink,
    navigateTo,
    handleLinkClick,
    handlePopState,
    bindNavigationEvents
  };
}

export { createNavigationAdapter };