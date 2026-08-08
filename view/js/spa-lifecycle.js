// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

// @license magnet:?xt=urn:btih:0b31508aeb0634b347b8270c7bee4d411b5d4109&dn=agpl-3.0.txt AGPLv3-or-later

/**
 * Trigger spa:document:ready event.
 */
function triggerSPADocumentReady() {
  window.dispatchEvent(new CustomEvent('spa:document:ready'));
}

/**
 * Trigger spa:window:load event.
 */
function triggerSPAWindowLoad() {
  console.debug('[SPA Router] triggerSPAWindowLoad: dispatching spa:window:load');
  window.dispatchEvent(new CustomEvent('spa:window:load'));
}

/**
 * Re-initialize dynamic content after SPA navigation.
 *
 * @param {(scripts: Array, context: string) => void} executeScriptsFn
 */
function reinitializeDynamicContent(executeScriptsFn) {
  if (window.__spa_bodyScripts?.length > 0) {
    console.debug('[SPA Router] reinitializeDynamicContent: Executing ' + window.__spa_bodyScripts.length + ' body scripts');
    window.__spa_executing_fragment_scripts = true;
    executeScriptsFn(window.__spa_bodyScripts, 'body-scripts');
    window.__spa_executing_fragment_scripts = false;
    window.__spa_bodyScripts = [];
  }

  const spaNavigateEvent = new CustomEvent('spa:navigate', {
    detail: { path: window.location.pathname }
  });
  window.dispatchEvent(spaNavigateEvent);

  console.debug('[SPA Router] reinitializeDynamicContent: dispatching spa:window:load');
  const spaDocumentLoadEvent = new CustomEvent('spa:window:load');
  window.dispatchEvent(spaDocumentLoadEvent);

  const spaDocumentReadyEvent = new CustomEvent('spa:document:ready');
  window.dispatchEvent(spaDocumentReadyEvent);

  const initInfiniteScrollEvent = new CustomEvent('spa:initInfiniteScroll');
  window.dispatchEvent(initInfiniteScrollEvent);

  if (typeof NavUpdate === 'function') {
    console.debug('[SPA Router] Calling NavUpdate after navigation');
    NavUpdate();
  }
}

export {
  triggerSPADocumentReady,
  triggerSPAWindowLoad,
  reinitializeDynamicContent
};