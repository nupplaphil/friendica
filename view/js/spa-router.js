// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

// @license magnet:?xt=urn:btih:0b31508aeb0634b347b8270c7bee4d411b5d4109&dn=agpl-3.0.txt AGPLv3-or-later

/**
 * SPA Router for Friendica
 * Client-side routing implementation for /network, /display, /profile
 * Keeps footer static (for XMPP addon compatibility)
 * Vanilla-JS implementation - no external dependencies
 */

// ============================================
// FEATURE DETECTION
// ============================================

/**
 * Check if browser supports SPA features
 * @returns {boolean} True if History API and Fetch are supported
 */
const supportsSPA = window.history && window.history.pushState && window.fetch;

// ============================================
// GLOBAL STATE
// ============================================

// Track the final URL after redirects for display page detection
let lastFinalUrl = null;

// Track the router version to detect updates
const clientRouterVersion = typeof window.__spa_router_version !== 'undefined' ? window.__spa_router_version : null;

// ============================================
// CONFIGURATION
// ============================================

const SPA_CONFIG = {
  enabled: true,
  excludedRoutes: [
    '/delegation',
    '/settings/display', // The ordering of the channels currently does not work with SPA, so we exclude it for now
  ],
  scrollToTopOnNavigate: true
};

// ============================================
// ROUTE HANDLING
// ============================================

/**
 * Check if a URL path is an SPA route
 * @param {string} path - The URL pathname
 * @returns {boolean} True if route should use SPA
 */
function isSPARoute(path) {
  return !SPA_CONFIG.excludedRoutes.some(route => {
    // Exact match or starts with route + /
    return path === route || path.startsWith(route + '/');
  });
}

/**
 * Check if a link is an internal link (same origin)
 * @param {HTMLAnchorElement} link - The anchor element
 * @returns {boolean} True if internal link
 */
function isInternalLink(link) {
  const href = link.getAttribute('href');
  if (!href) return false;

  // Skip anchor links, javascript: and mailto:
  if (href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:')) {
    return false;
  }
  
  // Check if same origin
  try {
    const url = new URL(href, window.location.href);
    return url.origin === window.location.origin;
  } catch (e) {
    return false;
  }
}

/**
 * Handle link clicks
 * @param {Event} e - Click event
 */
function handleLinkClick(e) {
  const link = e.target.closest('a');
  if (!link) {
    return;
  }
  
  let href = link.getAttribute('href');
  
  // Ignore anchor-only links (e.g., href="#" or href="#back-to-top")
  if (href && href.startsWith('#')) {
    console.debug('[SPA Router] Click: Anchor link, allowing default behavior');
    return;
  }
  
  // Ignore links that are meant to open modals (e.g., modal-open class)
  // These are handled by separate modal JavaScript handlers
  if (link.classList.contains('modal-open')) {
    console.debug('[SPA Router] Click: Modal link, allowing default/modal behavior');
    return;
  }

  // Ignore links with data-fancybox attribute (for Fancybox lightbox)
  // These are handled by Fancybox JavaScript
  // Push a state marker so we can detect back navigation from Fancybox
  if (link.hasAttribute('data-fancybox')) {
    console.debug('[SPA Router] Click: Fancybox link, pushing marker state and allowing default/fancybox behavior');
    history.pushState({ __fancyboxMarker: true }, '', window.location.href);
    return;
  }

  // Ignore links with inline event handlers (onclick, etc.)
  // These are handled by custom JavaScript and should not use SPA
  if (link.hasAttribute("onclick") || link.onclick || link.hasAttribute("data-spa-ignore")) {
    console.debug("[SPA Router] Click: Link has event handler or data-spa-ignore, allowing default behavior");
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
 * Set focus to content area for accessibility
 * Scrolls to top and sets focus on the content element
 * This is better for screen readers and keyboard navigation
 */
function scrollToTopInstant() {
  console.debug('[SPA Router] scrollToTopInstant: focusing on content element for accessibility');
  const contentElement = document.getElementById('content');
  
  // Save previous scroll behavior
  const html = document.documentElement;
  const previousBehavior = html.style.scrollBehavior;
  
  // Scroll to top instantly
  html.style.scrollBehavior = 'auto';
  window.scrollTo(0, 0);
  html.style.scrollBehavior = previousBehavior;
  
  // Set focus to content element for accessibility
  if (contentElement) {
    contentElement.setAttribute('tabindex', '-1'); // Make element focusable
    contentElement.focus();
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
  
  // Check if route is SPA-capable
  if (!isSPARoute(path)) {
    console.debug('[SPA Router] Navigate: Not an SPA route, falling back to full reload');
    window.location.href = url;
    return;
  }
  
  console.debug('[SPA Router] Navigate: SPA route detected, pushing state and loading content');
  
  // Dispatch event to pause live updates before navigation
  const beforeEvent = new CustomEvent('spa:beforeNavigate', {
    detail: { path: path, url: url }
  });
  window.dispatchEvent(beforeEvent);
  
  // Update History API
  history.pushState({ path, spa: true, __friendicaSPA: true }, '', url);

  // Load content
  loadContent(url);
}

// ============================================
// CONTENT LOADING
// ============================================

/**
 * Show timeout modal overlay
 * Displays a modal dialog that can be clicked away
 */
function showTimeoutModal() {
  console.debug('[SPA Router] showTimeoutModal: Displaying timeout overlay');
  
  hideLoading();
  
  // Check if modal already exists
  if (document.getElementById('spa-timeout-modal')) {
    console.debug('[SPA Router] showTimeoutModal: Modal already exists');
    return;
  }
  
  // Get translated texts from PHP
  const title = window.spaErrorTexts.timeout;
  const message = window.spaErrorTexts.timeout_message;
  const closeText = window.spaErrorTexts.close;
  
  // Create modal overlay
  const modal = document.createElement('div');
  modal.id = 'spa-timeout-modal';
  modal.className = 'spa-modal-overlay';
  
  // Create modal content
  const content = document.createElement('div');
  content.className = 'spa-modal-content';
  
  // Create heading
  const heading = document.createElement('h2');
  heading.textContent = title;
  
  // Create message paragraph
  const messageElement = document.createElement('p');
  messageElement.textContent = message;
  
  // Create close button
  const closeButton = document.createElement('button');
  closeButton.textContent = closeText;
  closeButton.className = 'btn btn-primary spa-modal-close-btn';
  closeButton.onclick = function() {
    dismissTimeoutModal();
  };
  
  content.appendChild(heading);
  content.appendChild(messageElement);
  content.appendChild(closeButton);
  
  modal.appendChild(content);
  
  // Close modal when clicking on overlay (outside content)
  modal.addEventListener('click', function(e) {
    if (e.target === modal) {
      dismissTimeoutModal();
    }
  });
  
  // Add modal to body
  document.body.appendChild(modal);
  
  // Also listen for Escape key
  const escapeHandler = function(e) {
    if (e.key === 'Escape' || e.keyCode === 27) {
      dismissTimeoutModal();
    }
  };
  document.addEventListener('keydown', escapeHandler);
  
  // Store reference to clean up
  modal._escapeHandler = escapeHandler;
}

/**
 * Dismiss the timeout modal
 */
function dismissTimeoutModal() {
  const modal = document.getElementById('spa-timeout-modal');
  if (modal) {
    if (modal._escapeHandler) {
      document.removeEventListener('keydown', modal._escapeHandler);
    }
    modal.remove();
    console.debug('[SPA Router] dismissTimeoutModal: Modal removed');
  }
}

/**
 * Handle HTTP error responses
 * @param {number} status - HTTP status code
 * @param {string} url - The original request URL
 */
function handleHttpError(status, url) {
  console.debug('[SPA Router] handleHttpError: status=', status, 'url=', url);
  
  // 401: redirect to login
  if (status === 401) {
    console.debug('[SPA Router] handleHttpError: 401 Unauthorized - redirecting to login');
    window.location.href = '/login?return_path=' + encodeURIComponent(url);
  }
}

/**
 * Load content via AJAX
 * @param {string} url - The URL to load
 */
function loadContent(url) {
  console.debug('[SPA Router] LoadContent: url=', url);
  
  // Use normal URL without SPA parameter - server returns full HTML
  const fetchUrl = new URL(url, window.location.href);
  console.debug('[SPA Router] LoadContent: fetching URL=', fetchUrl.toString());
  
  // Track the final URL after all redirects
  let finalUrl = fetchUrl.toString();

  showFetching();
  fetch(fetchUrl, {
    headers: {
      'Accept': 'text/html'
    },
    credentials: 'include' // Send cookies for same-origin requests
  })
  .then(async (response) => {
    
    console.debug('[SPA Router] LoadContent: Response received, status=', response.status, 'response.url=', response.url);
    
    // Check Content-Type header - only text/html is allowed for SPA
    const contentType = response.headers.get('Content-Type') || response.headers.get('content-type') || '';
    if (!contentType.includes('text/html')) {
      console.debug('[SPA Router] LoadContent: Invalid Content-Type:', contentType, '- falling back to full reload');
      window.location.href = url;
      return null;
    }
    
    // Get the final URL after any redirects - with automatic following, response.url contains it
    if (response.url && response.url !== fetchUrl.toString()) {
      finalUrl = response.url;
      console.debug('[SPA Router] LoadContent: Final URL after redirects:', finalUrl);
    }
    
    // Special handling for 401 and 504
    if (response.status === 401) {
      console.debug('[SPA Router] LoadContent: 401 Unauthorized - redirecting to login');
      handleHttpError(401, url);
      return null;
    }
    
    if (response.status === 504) {
      console.debug('[SPA Router] LoadContent: 504 Gateway Timeout - showing modal');
      showTimeoutModal();
      return null;
    }
    
    // For all other responses (including other HTTP errors like 404, 500, etc.)
    // load the server's error page content via SPA
    showReceiving();
    
    const html = await response.text();
    
    console.debug('[SPA Router] LoadContent: HTML received, type:', typeof html, 'length:', html ? html.length : 0, 'finalUrl:', finalUrl);
    
    showProcessing();
    
    // Validate that html is a string
    if (typeof html !== 'string') {
      console.error('[SPA Router] LoadContent: html is not a string! type:', typeof html, 'value:', html);
      throw new Error('Response body is not a string');
    }
    
    // Update history with the final URL if there were redirects
    if (finalUrl !== fetchUrl.toString()) {
      console.debug('[SPA Router] LoadContent: Updating history to final URL:', finalUrl);
      history.replaceState({ path: new URL(finalUrl).pathname, spa: true, __friendicaSPA: true }, '', finalUrl);
    }
    
    // Replace content of the three main containers
    // Pass the final URL to detect display pages after redirects
    replaceContainerContent(html, finalUrl);
    hideLoading();
    
    console.debug('[SPA Router] LoadContent: Process completed successfully');
    return html;
  })
  .catch(error => {
    hideLoading();
    console.error('[SPA Router] Error loading content:', error);
    console.error('[SPA Router] Error stack:', error.stack);
    console.error('[SPA Router] Error name:', error.name);
    console.error('[SPA Router] Error message:', error.message);
    console.error('[SPA Router] Error details:', {
      url: url,
      fetchUrl: fetchUrl.toString(),
      finalUrl: finalUrl,
      error: error
    });
    
    // Handle timeout errors with modal - only for server 504, not for client timeout
    // Client timeout (AbortError) just dismisses the delay modal, does not show timeout modal
    if (error.name === 'AbortError') {
      console.debug('[SPA Router] LoadContent: AbortError (client timeout) - dismissing delay modal only');
      // Don't show timeout modal for client timeout, only dismiss delay modal
    } else if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
      // Network error - fall back to full reload
      console.debug('[SPA Router] LoadContent: Network error - falling back to reload');
      window.location.href = url;
    } else {
      // Fallback: Full page reload for other errors
      console.debug('[SPA Router] Falling back to full page reload for URL:', url);
      window.location.href = url;
    }
  });
}

// ============================================
// CONTAINER CONTENT REPLACEMENT
// ============================================

/**
 * Remove all tooltip elements to prevent ghost tooltips after SPA navigation
 * Targets common tooltip classes and dynamically created tooltip elements
 */
function cleanupTooltips() {
  const tooltipSelectors = [
    '.tooltip',                    // Standard tooltip class
    '[class*="tooltip"]',          // Classes containing "tooltip"
    '.ui-tooltip',                // jQuery UI tooltips
    '.popover',                   // Bootstrap popovers
    '[role="tooltip"]',           // ARIA role tooltips
    '.fancybox-wrap',             // Fancybox
    '.colorbox',                  // Colorbox
    '.jGrowl',                    // jGrowl notifications
    '.panel'                      // Friendica permission panel (from lockview)
  ];

  tooltipSelectors.forEach(selector => {
    const elements = document.querySelectorAll(selector);
    // Convert NodeList to Array for compatibility with older browsers
    const elementsArray = Array.prototype.slice.call(elements);
    elementsArray.forEach(el => {
      console.debug('[SPA Router] Cleanup: Removing tooltip element:', selector);
      el.remove();
    });
  });
}

/**
 * Replace content of the three main containers: nav#topbar-first, div#topbar-second, and main
 * This approach preserves the DOM structure while updating the content
 * @param {string} htmlString - New content HTML (may be full document or fragment)
 * @param {string} finalUrl - The final URL after following redirects (optional)
 */
function replaceContainerContent(htmlString, finalUrl = null) {
  console.debug('[SPA Router] ReplaceContent: starting replacement');
  
  // Clean up any existing tooltips first to prevent ghost elements
  cleanupTooltips();
  
  // Validate htmlString is a string
  if (typeof htmlString !== 'string') {
    console.error('[SPA Router] ReplaceContent: htmlString is not a string!');
    return;
  }
  
  // Store the final URL for potential redirect handling
  lastFinalUrl = finalUrl || window.location.href;

  // Use DOMParser for safer and more robust parsing
  const parser = new DOMParser();
  const newDoc = parser.parseFromString(htmlString, 'text/html');
  
  // 1. Update document title
  const newTitle = newDoc.querySelector('title');
  if (newTitle) {
    document.title = newTitle.textContent;
  }

  // Check for router version update in the new content
  const newRouterVersion = newDoc.querySelector('script[data-spa-version]');
  if (newRouterVersion) {
    const serverVersion = newRouterVersion.getAttribute('data-spa-version');
    if (clientRouterVersion && serverVersion && serverVersion !== clientRouterVersion) {
      console.debug('[SPA Router] Version mismatch detected (Client:', clientRouterVersion, 'Server:', serverVersion, ') - triggering full reload');
      window.location.reload();
      return;
    }
  }

  // 2. Identify all resources from head and body
  const globalScripts = [];
  const bodyScripts = [];
  const externalScriptPromises = [];
  
  // Collect all script and link/style elements from the whole response
  const resourceElements = newDoc.querySelectorAll('head > link, head > script, head > style, body script, body link');
  
  // 3. Define the main containers to update
  const containerSelectors = ['nav#topbar-first', 'div#topbar-second', 'main'];
  
  // 4. PERFORM CONTENT REPLACEMENT (Main body)
  containerSelectors.forEach(selector => {
    const newDiv = newDoc.querySelector(selector);
    const oldDiv = document.querySelector(selector);
    
    if (newDiv && oldDiv) {
      console.debug('[SPA Router] ReplaceContent: Updating container', selector);
      
      // Extract scripts within this container to handle manually.
      // We pass both globalScripts and bodyScripts array to ensure that
      // scripts like infinite_scroll (which contain definitions) are 
      // correctly classified as global headers even if they happen to 
      // be located inside a body container.
      const inlineScripts = newDiv.querySelectorAll('script:not([src])');
      inlineScripts.forEach(script => {
        const content = script.textContent.trim();
        if (content) classifyScript(content, globalScripts, bodyScripts);
        script.parentNode.removeChild(script);
      });

      // Special handling for scrolling on main container
      if (selector === 'main') {
        const effectivePath = lastFinalUrl ? new URL(lastFinalUrl).pathname : window.location.pathname;
        if (SPA_CONFIG.scrollToTopOnNavigate && !effectivePath.includes('/display/')) {
          scrollToTopInstant();
        }
      }
      
      oldDiv.innerHTML = newDiv.innerHTML;
    }
  });

  // Handle scroll-loader
  const scrollLoaderNew = newDoc.querySelector('#scroll-loader');
  if (scrollLoaderNew) {
    const scrollLoaderOld = document.getElementById('scroll-loader');
    if (scrollLoaderOld) scrollLoaderOld.innerHTML = scrollLoaderNew.innerHTML;
  }

  // 5. SYNC HEAD (Links, Styles, External Scripts)
  resourceElements.forEach(el => {
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
        // Only add if not already present
        if (!document.querySelector('script[src="' + src + '"]')) {
          console.debug('[SPA Router] Adding external script:', src);
          const clone = document.createElement('script');
          
          // Copy all attributes
          Array.from(el.attributes).forEach(attr => {
            clone.setAttribute(attr.name, attr.value);
          });
          
          // Ensure scripts are executed in order
          clone.async = false;
          
          const p = new Promise(resolve => {
            clone.onload = () => {
              console.debug('[SPA Router] Script loaded successfully:', src);
              resolve();
            };
            clone.onerror = () => {
              console.warn('[SPA Router] Script failed to load:', src);
              resolve(); 
            };
            // Do NOT use a safety timeout here if we want to ensure order
          });
          externalScriptPromises.push(p);
          document.head.appendChild(clone);
        } else {
          // Script is already in DOM. If it's a library like fullcalendar, 
          // we might still need to wait for its load event if it was added 
          // in THIS same cycle (rare but possible).
        }
      } else {
        // It's an inline script from head (or already processed if from body)
        // Only classify if it hasn't been removed from content already
        if (el.parentNode && el.parentNode.tagName.toLowerCase() === 'head') {
          classifyScript(el.textContent.trim(), globalScripts, bodyScripts);
        }
      }
    }
  });

  // 6. EXECUTION LIFECYCLE
  window.__spa_executing_page_scripts = true;
  window.__spa_executing_fragment_scripts = false; 
  window.__spa_bodyScripts = bodyScripts;

  // Execute global-like headers first (vars, aStr, etc.)
  window.__spa_executing_fragment_scripts = true;
  executeScripts(globalScripts, 'global-head');
  
  // IMMEDIATELY re-initialize infinite scroll if the new page provides the config
  // (the 'infinite_scroll' object is usually in the global-head or body-scripts)
  if (typeof initInfiniteScroll === 'function') {
    initInfiniteScroll();
  }
  
  // Also dispatch the custom event for any other listeners
  window.dispatchEvent(new CustomEvent('spa:initInfiniteScroll'));
  
  window.__spa_executing_fragment_scripts = false;

  const runFinalAppInit = () => {
    window.__spa_reinit_phase = true;
    reinitializeDynamicContent();
    window.__spa_reinit_phase = false;
    window.__spa_executing_page_scripts = false;
  };

  if (externalScriptPromises.length > 0) {
    console.debug('[SPA Router] Waiting for', externalScriptPromises.length, 'scripts before reinit...');
    Promise.all(externalScriptPromises).then(() => {
      // Small timeout to ensure the browser has a chance to execute the code
      // of the newly loaded scripts before we trigger the reinitialization.
      setTimeout(runFinalAppInit, 10);
    });
  } else {
    runFinalAppInit();
  }
}

/**
 * Classify a script as either global (definitions) or body (execution)
 * @param {string} content 
 * @param {Array} globalScripts 
 * @param {Array} bodyScripts 
 */
function classifyScript(content, globalScripts, bodyScripts) {
  if (!content) return;

  // More robust check for variable definitions/assignments that should happen early
  // This covers var, let, const at start (after optional comments/whitespace)
  // or assignments to common global patterns like aStr, infinite_scroll etc.
  // Also check for common Friendica global functions/variables
  const isGlobal = content.match(/^(\/\*[\s\S]*?\*\/|\/\/[^\n]*\n|\s)*(var|let|const|aStr|infinite_scroll|NavUpdate|onPageLoad|calendar_api|event_api)\b/);
  
  if (isGlobal) {
    globalScripts.push(content);
  } else {
    bodyScripts.push(content);
  }
}

/**
 * Execute a list of script contents
 * @param {Array} scripts 
 * @param {string} context 
 */
function executeScripts(scripts, context) {
  if (!scripts || scripts.length === 0) return;
  
  console.debug('[SPA Router] executeScripts: Executing ' + scripts.length + ' ' + context + ' scripts');
  
  // We combine all scripts of the same category (global or body) into a single 
  // execution block. This ensures they share a lexical scope, allowing one 
  // script to use 'const' or 'let' variables defined in another script 
  // from the same page load.
  const combinedContent = scripts.join('\n\n/* --- Next Script --- */\n\n');

  try {
    // 1. Lexical Scoping:
    // We wrap the combined scripts in an anonymous block { ... } to prevent 
    // "redeclaration" errors (TypeError: redeclaration of const) when 
    // navigating between pages in SPA mode. This block creates a new 
    // lexical scope for each page load.
    
    // 2. Variable & Function promotion to global scope:
    // We transform the code to ensure that functions and variables defined 
    // inside our block become available globally.
    const promotedContent = combinedContent
      // Promote variables: const/let/var name = ... -> window.name = ...
      // We also handle 'var name = ...' to ensure it's explicitly global even 
      // when inside our lexical block.
      .replace(/(^|[^a-zA-Z0-9_$])(const|let|var)\s+([a-zA-Z0-9_$]+)\s*=/g, '$1window.$3 =')
      // Promote direct assignments to window if they use var/const/let:
      // var window.name = ... (some scripts might do this)
      .replace(/(^|[^a-zA-Z0-9_$])(const|let|var)\s+window\.([a-zA-Z0-9_$]+)\s*=/g, '$1window.$3 =')
      // Promote named functions to window
      .replace(/(^|[;{}\s])(function|async\s+function)\s+([a-zA-Z0-9_$]+)\s*\(/g, (match, prefix, funcType, funcName) => {
        return `${prefix}window.${funcName} = ${funcType} ${funcName}(`;
      });

    // 3. Runtime Safety: 
    // We wrap the whole block in a try-catch. This is important because 
    // assignments to global variables that were previously defined as 'const' 
    // during the initial page load will throw a TypeError. We want to catch 
    // these gracefully so other scripts can continue.
    const wrappedContent = 'try {\n{\n' + promotedContent + '\n}\n} catch (e) { console.debug("[SPA Router] Handled error in scripts:", e.message); }';

    const scriptEl = document.createElement('script');
    scriptEl.textContent = wrappedContent;
    document.head.appendChild(scriptEl);
    document.head.removeChild(scriptEl);
  } catch (e) {
    console.error('[SPA Router] Error executing combined ' + context + ' scripts:', e);
  }
}

// ============================================
// EVENT LISTENERS & REINITIALIZATION
// ============================================

/**
 * Re-initialize dynamic content after SPA navigation
 * This is important for elements that need event listeners
 */
function reinitializeDynamicContent() {
  // Execute body scripts (including widget init scripts) after DOM insertion
  if (window.__spa_bodyScripts && window.__spa_bodyScripts.length > 0) {
    console.debug('[SPA Router] reinitializeDynamicContent: Executing ' + window.__spa_bodyScripts.length + ' body scripts');
    window.__spa_executing_fragment_scripts = true;
    executeScripts(window.__spa_bodyScripts, 'body-scripts');
    window.__spa_executing_fragment_scripts = false;
    // Clear the stored scripts after execution
    window.__spa_bodyScripts = [];
  }

  // Dispatch custom events for other scripts to hook into
  const spaNavigateEvent = new CustomEvent('spa:navigate', {
    detail: { path: window.location.pathname }
  });
  window.dispatchEvent(spaNavigateEvent);
  
  // Trigger theme reinitialization for elements like #back-to-top
  const themeReloadEvent = new CustomEvent('theme:reload');
  window.dispatchEvent(themeReloadEvent);
  
  // Trigger infinite scroll reinitialization for network pages
  const initInfiniteScrollEvent = new CustomEvent('spa:initInfiniteScroll');
  window.dispatchEvent(initInfiniteScrollEvent);
  
  // Trigger NavUpdate to check for unread posts immediately after SPA navigation
  if (typeof NavUpdate === 'function') {
    console.debug('[SPA Router] Calling NavUpdate after navigation');
    NavUpdate();
  }
}

/**
 * Handle initial page load
 */
function handleInitialLoad() {
  // Initial content is already there, just initialize
  const event = new CustomEvent('spa:ready', {
    detail: { path: window.location.pathname }
  });
  window.dispatchEvent(event);
}

/**
 * Handle browser back/forward navigation
 * @param {Event} e - Popstate event
 */
function handlePopState(e) {
  console.debug('[SPA Router] PopState: state=', e.state, 'hash=', window.location.hash);
  
  if (e.state && e.state.spa && e.state.__friendicaSPA) {
    console.debug('[SPA Router] PopState: SPA navigation detected, loading', window.location.href);
    
    // Dispatch event to pause live updates before popstate navigation
    const beforeEvent = new CustomEvent('spa:beforeNavigate', {
      detail: { path: window.location.pathname, url: window.location.href }
    });
    window.dispatchEvent(beforeEvent);
    
    loadContent(window.location.href);
  } else {
    console.debug('[SPA Router] PopState: Not an SPA state or not from our router, ignoring');
  }
}

// ============================================
// MAIN INITIALIZATION
// ============================================

/**
 * Initialize SPA Router
 */
function initSPARouter() {
  console.debug('[SPA Router] Initializing... supportsSPA=', supportsSPA, 'enabled=', SPA_CONFIG.enabled);
  
  if (!supportsSPA || !SPA_CONFIG.enabled) {
    console.debug('[SPA Router] Not supported or disabled');
    return;
  }
  
  // Initial load handler
  window.addEventListener('DOMContentLoaded', handleInitialLoad);
  
  // Intercept link clicks (using event delegation)
  document.addEventListener('click', handleLinkClick);
  
  // Browser navigation (back/forward)
  window.addEventListener('popstate', handlePopState);
  
  console.debug('[SPA Router] Initialized successfully');
}

// ============================================
// FEATURE DETECTION & FALLBACK
// ============================================

// Check if browser supports required features
if (!supportsSPA) {
  console.debug('[SPA Router] Browser does not support SPA features. Using fallback.');
} else {
  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSPARouter);
  } else {
    initSPARouter();
  }
}

// Export for testing/module use
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    initSPARouter,
    isSPARoute,
    isInternalLink,
    supportsSPA,
    SPA_CONFIG
  };
}
