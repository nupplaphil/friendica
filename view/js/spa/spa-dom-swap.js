// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

// @license magnet:?xt=urn:btih:0b31508aeb0634b347b8270c7bee4d411b5d4109&dn=agpl-3.0.txt AGPLv3-or-later

function createDomSwapPipeline(options) {
  const {
    clientRouterVersion,
    spaConfig,
    cleanupTooltips,
    classifyScript,
    executeScripts,
    reinitializeDynamicContent,
    scrollToTopInstant
  } = options;

  function getContainerSelectors() {
    const coreSelectors = Array.isArray(spaConfig.containerSelectorsCore) && spaConfig.containerSelectorsCore.length > 0
      ? spaConfig.containerSelectorsCore
      : ['nav#topbar-first', 'div#topbar-second', 'main'];

    const includeExtended = spaConfig.enableExtendedContainerSync !== false;
    const extendedSelectors = includeExtended && Array.isArray(spaConfig.extendedContainerSelectors)
      ? spaConfig.extendedContainerSelectors
      : [];

    return Array.from(new Set([...coreSelectors, ...extendedSelectors]));
  }

  function normalizeScriptSource(src) {
    if (!src) {
      return null;
    }

    try {
      const baseUrl = document.baseURI || (window.location.origin + '/');
      return new URL(src, baseUrl).toString();
    } catch (e) {
      return src;
    }
  }

  /**
   * Replace content of the main containers.
   * @param {string} htmlString - New content HTML (may be full document or fragment)
   * @param {string} finalUrl - The final URL after following redirects (optional)
   */
  function replaceContainerContent(htmlString, finalUrl = null) {
    console.debug('[SPA Router] ReplaceContent: starting replacement');

    cleanupTooltips();

    if (typeof htmlString !== 'string') {
      console.error('[SPA Router] ReplaceContent: htmlString is not a string!');
      return;
    }

    const effectiveFinalUrl = finalUrl || window.location.href;

    const parser = new DOMParser();
    const newDoc = parser.parseFromString(htmlString, 'text/html');

    const newTitle = newDoc.querySelector('title');
    if (newTitle) document.title = newTitle.textContent;

    const newRouterVersion = newDoc.querySelector('script[data-spa-version]');
    if (newRouterVersion) {
      const serverVersion = newRouterVersion.getAttribute('data-spa-version');
      if (clientRouterVersion && serverVersion && serverVersion !== clientRouterVersion) {
        console.debug('[SPA Router] Version mismatch detected (Client:', clientRouterVersion, 'Server:', serverVersion, ') - triggering full reload');
        window.location.reload();
        return;
      }
    }

    const globalScripts = [];
    const bodyScripts = [];
    const externalScriptPromises = [];

    const resourceElements = newDoc.querySelectorAll('head > link, head > script, head > style, body script, body link');
    const effectivePath = new URL(effectiveFinalUrl, window.location.href).pathname;

    const containerSelectors = getContainerSelectors();

    containerSelectors.forEach(selector => {
      const newDiv = newDoc.querySelector(selector);
      const oldDiv = document.querySelector(selector);

      if (newDiv && oldDiv) {
        console.debug('[SPA Router] ReplaceContent: Updating container', selector);

        const inlineScripts = newDiv.querySelectorAll('script:not([src])');
        for (const script of inlineScripts) {
          const content = script.textContent.trim();
          if (content) classifyScript(content, globalScripts, bodyScripts, script, 'container');
          script.parentNode.removeChild(script);
        }

        if (selector === 'main') {
          if (spaConfig.scrollToTopOnNavigate && !effectivePath.includes('/display/')) {
            scrollToTopInstant();
          }
        }

        oldDiv.innerHTML = newDiv.innerHTML;
      }
    });

    const scrollLoaderNew = newDoc.querySelector('#scroll-loader');
    if (scrollLoaderNew) {
      const scrollLoaderOld = document.getElementById('scroll-loader');
      if (scrollLoaderOld) scrollLoaderOld.innerHTML = scrollLoaderNew.innerHTML;
    }

    const newPageScripts = new Set();
    const newPageResourceElements = newDoc.querySelectorAll('head > script[src], body script[src]');
    newPageResourceElements.forEach(el => {
      const src = normalizeScriptSource(el.getAttribute('src'));
      if (src) {
        newPageScripts.add(src);
      }
    });

    if (typeof window.clearSPASubscribers === 'function') {
      const cleanupStats = window.clearSPASubscribers(newPageScripts);
      console.debug('[SPA Router] Subscriber cleanup stats:', cleanupStats);
    }

    const currentScripts = document.querySelectorAll('script[src]');
    console.debug('[SPA Router] Found', currentScripts.length, 'external script(s) in current DOM');
    console.debug('[SPA Router] New page has', newPageScripts.size, 'external script(s)');
    currentScripts.forEach(script => {
      const src = normalizeScriptSource(script.getAttribute('src') || script.src);
      if (newPageScripts.has(src) || script.hasAttribute('data-spa-persistent')) {
        console.debug('[SPA Router] Keeping script (in new page or persistent):', src);
        return;
      }

      console.debug('[SPA Router] Removing script (not in new page, not persistent):', src);
      script.parentNode.removeChild(script);
    });

    for (const el of resourceElements) {
      const tag = el.tagName.toLowerCase();

      if (tag === 'link' || tag === 'style') {
        const href = el.getAttribute('href');
        const selector = tag + (href ? `[href="${href}"]` : '');
        if (!document.head.querySelector(selector)) {
          console.debug('[SPA Router] Adding style/link:', selector);
          document.head.appendChild(el.cloneNode(true));
        }
      } else if (tag === 'script') {
        const src = el.getAttribute('src');
        if (src) {
          const normalizedSrc = normalizeScriptSource(src);
          if (!document.querySelector(`script[src="${src}"]`) && !document.querySelector(`script[src="${normalizedSrc}"]`)) {
            console.debug('[SPA Router] Adding script (not in DOM):', src);
            const clone = document.createElement('script');

            Array.from(el.attributes).forEach(attr => {
              clone.setAttribute(attr.name, attr.value);
            });

            clone.async = false;

            const p = new Promise(resolve => {
              clone.onload = () => {
                console.debug('[SPA Router] Script loaded:', src);
                resolve();
              };
              clone.onerror = () => {
                console.warn('[SPA Router] Script load error:', src);
                resolve();
              };
            });
            externalScriptPromises.push(p);
            document.head.appendChild(clone);
          } else {
            console.debug('[SPA Router] Script already in DOM, skipping add:', normalizedSrc || src);
          }
        } else {
          if (el.parentNode && el.parentNode.tagName.toLowerCase() === 'head') {
            classifyScript(el.textContent.trim(), globalScripts, bodyScripts, el, 'head');
          }
        }
      }
    }

    window.__spa_executing_page_scripts = true;
    window.__spa_executing_fragment_scripts = false;
    window.__spa_bodyScripts = bodyScripts;

    window.__spa_executing_fragment_scripts = true;
    executeScripts(globalScripts, 'global-head');

    if (typeof initInfiniteScroll === 'function') {
      initInfiniteScroll();
    }

    window.__spa_executing_fragment_scripts = false;

    const runFinalAppInit = () => {
      window.__spa_reinit_phase = true;
      reinitializeDynamicContent(executeScripts);
      window.__spa_reinit_phase = false;
      window.__spa_executing_page_scripts = false;
    };
    if (externalScriptPromises.length > 0) {
      console.debug('[SPA Router] Waiting for', externalScriptPromises.length, 'scripts before reinit...');
      Promise.all(externalScriptPromises).then(() => {
        setTimeout(runFinalAppInit, 10);
      });
    } else {
      runFinalAppInit();
    }
  }

  return {
    replaceContainerContent
  };
}

export { createDomSwapPipeline };