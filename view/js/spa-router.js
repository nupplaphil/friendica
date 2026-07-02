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

// ============================================
// CONFIGURATION
// ============================================

const SPA_CONFIG = {
  enabled: true,
  routes: [
    '/community',
    '/contact',
    '/display',
    '/message',
    '/network',
    '/notification',
    '/profile',
    '/search',
  ],
  spaHeader: 'X-Friendica-SPA',
  spaParam: 'spa',
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
  return SPA_CONFIG.routes.some(route => {
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
    console.log('[SPA Router] Click: Anchor link, allowing default behavior');
    return;
  }
  
  // Ignore links that are meant to open modals (e.g., modal-open class)
  // These are handled by separate modal JavaScript handlers
  if (link.classList.contains('modal-open')) {
    console.log('[SPA Router] Click: Modal link, allowing default/modal behavior');
    return;
  }

  // Ignore links with data-fancybox attribute (for Fancybox lightbox)
  // These are handled by Fancybox JavaScript
  if (link.hasAttribute('data-fancybox')) {
    console.log('[SPA Router] Click: Fancybox link, allowing default/fancybox behavior');
    return;
  }

  // Ignore links with inline event handlers (onclick, etc.)
  // These are handled by custom JavaScript and should not use SPA
  if (link.hasAttribute("onclick") || link.onclick || link.hasAttribute("data-spa-ignore")) {
    console.log("[SPA Router] Click: Link has event handler or data-spa-ignore, allowing default behavior");
    return;
  }
  
  console.log('[SPA Router] Click: Found anchor, href=', href);
  
  if (!isInternalLink(link)) {
    console.log('[SPA Router] Click: Not an internal link, skipping');
    return;
  }

  if (!href.startsWith('http://') && !href.startsWith('https://') && !href.startsWith('/')) {
    href = '/' + href;
  }
  
  // Allow middle-click, ctrl-click, cmd-click to open in new tab
  if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey) {
    console.log('[SPA Router] Click: Modified click (middle/ctrl/cmd/shift), skipping');
    return;
  }

  console.log('[SPA Router] Click: Preventing default, navigating to', href);
  e.preventDefault();
  showLoading();
  navigateTo(href);
}

/**
 * Jump to top instantly, ignoring CSS smooth scroll.
 */
function scrollToTopInstant() {
  console.log('[SPA Router] scrollToTopInstant: scrolling to top');
  const html = document.documentElement;
  const previousBehavior = html.style.scrollBehavior;

  html.style.scrollBehavior = 'auto';
  window.scrollTo(0, 0);
  html.style.scrollBehavior = previousBehavior;
}

/**
 * Navigate to URL using SPA
 * @param {string} url - The URL to navigate to
 */
function navigateTo(url) {
  console.log('[SPA Router] Navigate: url=', url);
  
  const path = new URL(url, window.location.href).pathname;
  console.log('[SPA Router] Navigate: parsed path=', path);
  
  // Check if route is SPA-capable
  if (!isSPARoute(path)) {
    console.log('[SPA Router] Navigate: Not an SPA route, falling back to full reload');
    hideLoading();
    window.location.href = url;
    return;
  }
  
  console.log('[SPA Router] Navigate: SPA route detected, pushing state and loading content');
  
  // Dispatch event to pause live updates before navigation
  const beforeEvent = new CustomEvent('spa:beforeNavigate', {
    detail: { path: path, url: url }
  });
  window.dispatchEvent(beforeEvent);
  
  // Update History API
  history.pushState({ path, spa: true }, '', url);
  
  // Load content
  loadContent(url);
}

// ============================================
// CONTENT LOADING
// ============================================

/**
 * Load content via AJAX
 * @param {string} url - The URL to load
 */
function loadContent(url) {
  console.log('[SPA Router] LoadContent: url=', url);
  
  // Use normal URL without SPA parameter - server returns full HTML
  const fetchUrl = new URL(url, window.location.href);
  console.log('[SPA Router] LoadContent: fetching URL=', fetchUrl.toString());
  
  // Track the final URL after all redirects
  let finalUrl = fetchUrl.toString();
  let timeoutId;
  const abortController = new AbortController();
  
  // Set timeout
  timeoutId = setTimeout(() => {
    console.log('[SPA Router] LoadContent: Timeout after 30000ms');
    abortController.abort();
  }, 30000);

  fetch(fetchUrl, {
    headers: {
      'Accept': 'text/html'
    },
    credentials: 'include', // Send cookies for same-origin requests
    signal: abortController.signal
    // Note: redirect: 'follow' is the default behavior, no need to specify
  })
  .then(async (response) => {
    clearTimeout(timeoutId);
    
    console.log('[SPA Router] LoadContent: Response received, status=', response.status, 'response.url=', response.url);
    
    // Get the final URL after any redirects - with automatic following, response.url contains it
    if (response.url && response.url !== fetchUrl.toString()) {
      finalUrl = response.url;
      console.log('[SPA Router] LoadContent: Final URL after redirects:', finalUrl);
    }
    
    showReceiving();
    
    const checkedResponse = checkResponseStatus(response);
    const html = await checkedResponse.text();
    
    console.log('[SPA Router] LoadContent: HTML received, type:', typeof html, 'length:', html ? html.length : 0, 'finalUrl:', finalUrl);
    
    showRendering();
    
    // Validate that html is a string
    if (typeof html !== 'string') {
      console.error('[SPA Router] LoadContent: html is not a string! type:', typeof html, 'value:', html);
      throw new Error('Response body is not a string');
    }
    
    // Update history with the final URL if there were redirects
    if (finalUrl !== fetchUrl.toString()) {
      console.log('[SPA Router] LoadContent: Updating history to final URL:', finalUrl);
      history.replaceState({ path: new URL(finalUrl).pathname, spa: true }, '', finalUrl);
    }
    
    // Replace content of the three main containers
    // Pass the final URL to detect display pages after redirects
    replaceContainerContent(html, finalUrl);
    hideLoading();
    
    console.log('[SPA Router] LoadContent: Process completed successfully');
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
    
    // Show error in alert so user can see it (temporarily for debugging)
    const errorInfo = 'SPA Error:\n' +
      'Message: ' + (error.message || 'Unknown error') + '\n' +
      'Name: ' + (error.name || 'Unknown') + '\n' +
      'URL: ' + url + '\n' +
      'Final URL: ' + finalUrl + '\n' +
      'Fetch URL: ' + fetchUrl.toString();
    console.error('[SPA Router] Error info for alert:\n', errorInfo);
    alert(errorInfo);
    
    // Fallback: Full page reload
    console.log('[SPA Router] Falling back to full page reload for URL:', url);
    console.log('[SPA Router] LoadContent: Error handling completed');
    window.location.href = url;
  });
}

/**
 * Check if response is successful
 * @param {Response} response - Fetch response
 * @returns {Response} The response if OK
 */
function checkResponseStatus(response) {
  if (!response.ok) {
    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
  }
  return response;
}

// ============================================
// SCRIPT EXECUTION
// ============================================

/**
 * Execute inline script tags from HTML to set global variables
 * Only executes variable declarations (var, let, const) to avoid issues with
 * event handlers that reference DOM elements or jQuery objects no longer valid after SPA navigation.
 * @param {string} html - The HTML content
 */
function executeInlineScripts(html) {
  const tempDiv = document.createElement('div');
  tempDiv.innerHTML = html;
  
  const scripts = tempDiv.querySelectorAll('script:not([src])');
  console.log('[SPA Router] executeInlineScripts: Found ' + scripts.length + ' inline scripts');
  // Convert NodeList to Array for compatibility with older browsers
  const scriptsArray = Array.prototype.slice.call(scripts);
  scriptsArray.forEach((script, index) => {
    const scriptContent = script.textContent.trim();
    if (scriptContent) {
      // Only execute variable declarations (var, let, const) and important assignments
      if (scriptContent.startsWith('var ') ||
          scriptContent.startsWith('let ') ||
          scriptContent.startsWith('const ') ||
          scriptContent.trim().startsWith('infinite_scroll =') ||
          scriptContent.includes('infinite_scroll =')) {
        console.log('[SPA Router] executeInlineScripts: Executing var/let/const or infinite_scroll #' + index + ': ' + scriptContent.substring(0, 150));
        try {
          // Execute script in global scope by creating and removing a script element
          const scriptEl = document.createElement('script');
          scriptEl.textContent = scriptContent;
          document.head.appendChild(scriptEl);
          document.head.removeChild(scriptEl);
          console.log('[SPA Router] Executed inline script #' + index);
        } catch (e) {
          console.error('[SPA Router] Error executing inline script #' + index + ':', e);
        }
      } else {
        console.log('[SPA Router] executeInlineScripts: Skipping non-declaration script #' + index + ': ' + scriptContent.substring(0, 80));
      }
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
      console.log('[SPA Router] Cleanup: Removing tooltip element:', selector);
      el.remove();
    });
  });
}

/**
 * Replace content of the three main containers: nav#topbar-first, div#topbar-second, and main
 * This approach preserves the DOM structure while updating the content
 * @param {string} html - New content HTML (may be full document or fragment)
 * @param {string} finalUrl - The final URL after following redirects (optional)
 */
function replaceContainerContent(html, finalUrl = null) {
  console.log('[SPA Router] ReplaceContent: Processing HTML, finalUrl:', finalUrl);
  
  // Clean up any existing tooltips first to prevent ghost elements
  cleanupTooltips();
  
  // Validate html is a string
  if (typeof html !== 'string') {
    console.error('[SPA Router] ReplaceContent: html is not a string! type:', typeof html, 'value:', html);
    html = '';
  }
  
  // Store the final URL for scrollToDisplayGuid
  if (finalUrl) {
    lastFinalUrl = finalUrl;
    console.log('[SPA Router] ReplaceContent: Set lastFinalUrl to:', lastFinalUrl);
  } else {
    lastFinalUrl = window.location.href;
    console.log('[SPA Router] ReplaceContent: Set lastFinalUrl to window.location.href:', lastFinalUrl);
  }
  
  // If response is a full HTML document, extract only the body content
  if (html && html.includes && html.includes('<body')) {
    console.log('[SPA Router] ReplaceContent: Extracting body content');
    const bodyMatch = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
    if (bodyMatch) {
      html = bodyMatch[1];
    }
  }
  
  // Execute inline scripts to set global variables (like profile_uid, netargs)
  executeInlineScripts(html);
  
  const tempDiv = document.createElement('div');
  tempDiv.innerHTML = html;
  
  // Extract and set title from HTML
  const titleMatch = html.match(/<title>([^<]*)<\/title>/i);
  if (titleMatch && titleMatch[1]) {
    console.log('[SPA Router] ReplaceContent: Setting title to', titleMatch[1]);
    document.title = titleMatch[1];
  }
  
  // Extract head elements (link, script, style) and move them to document head
  const headElements = tempDiv.querySelectorAll('link, script, style');
  console.log('[SPA Router] ReplaceContent: Found', headElements.length, 'head elements');
  // Convert NodeList to Array for compatibility with older browsers
  const headElementsArray = Array.prototype.slice.call(headElements);
  headElementsArray.forEach(el => {
    const existing = document.head.querySelector(
      `${el.tagName.toLowerCase()}[${el.src ? 'src' : el.href ? 'href' : 'data-spa-processed'}="${el.src || el.href || ''}"]`
    );
    if (!existing && !el.getAttribute('data-spa-processed')) {
      console.log('[SPA Router] ReplaceContent: Adding head element:', el.tagName, el.src || el.href || '(inline)');
      const clone = el.cloneNode(true);
      clone.setAttribute('data-spa-processed', 'true');
      document.head.appendChild(clone);
    }
  });
  
  // Define the three main containers to update
  const containers = [
    { selector: 'nav#topbar-first', newContent: tempDiv.querySelector('nav#topbar-first') },
    { selector: 'div#topbar-second', newContent: tempDiv.querySelector('div#topbar-second') },
    { selector: 'main', newContent: tempDiv.querySelector('main') }
  ];
  
  console.log('[SPA Router] ReplaceContent: Checking containers...');
  
  // Replace content of each container
  containers.forEach(({ selector, newContent }) => {
    if (newContent) {
      console.log('[SPA Router] ReplaceContent: Found new content for', selector);
      
      // Debug: Show content preview for topbar-second
      if (selector === 'div#topbar-second') {
        const preview = newContent.innerHTML.substring(0, 300).replace(/\n/g, ' ').replace(/\s+/g, ' ');
        console.log('[SPA Router] div#topbar-second content:', 
                    'length=', newContent.innerHTML.length, 
                    'preview="', preview, '"');
      }
      
      const oldContainer = document.querySelector(selector);
      if (oldContainer) {
        console.log('[SPA Router] ReplaceContent: Found old container for', selector, '- replacing innerHTML');

        // Check if this is a display page with GUID - if so, don't scroll to top
        // Use finalUrl if provided (for redirects), otherwise use window.location.pathname
        const effectivePath = finalUrl ? new URL(finalUrl).pathname : window.location.pathname;
        const isDisplayPageWithGuid = effectivePath.includes('/display/') && 
          (() => {
            const pathParts = effectivePath.split('/');
            const displayIndex = pathParts.indexOf('display');
            return displayIndex >= 0 && displayIndex + 1 < pathParts.length && pathParts[displayIndex + 1];
          })();
        
        console.log('[SPA Router] ReplaceContent: effectivePath:', effectivePath, 'isDisplayPageWithGuid:', isDisplayPageWithGuid);

        if (selector === 'main' && SPA_CONFIG.scrollToTopOnNavigate && !isDisplayPageWithGuid) {
          console.log('[SPA Router] ReplaceContent: Scrolling to top (not display page with GUID)');
          oldContainer.style.visibility = 'hidden';
          try {
            scrollToTopInstant();
            oldContainer.innerHTML = newContent.innerHTML;
          } finally {
            oldContainer.style.visibility = '';
          }
        } else if (selector === 'main' && isDisplayPageWithGuid) {
          console.log('[SPA Router] ReplaceContent: NOT scrolling to top (display page with GUID)');
          // Replace the inner HTML of the container
          oldContainer.innerHTML = newContent.innerHTML;
        } else {
          // Replace the inner HTML of the container
          oldContainer.innerHTML = newContent.innerHTML;
        }
      } else {
        console.warn('[SPA Router] ReplaceContent: Old container NOT found for', selector);
      }
    } else {
      console.warn('[SPA Router] ReplaceContent: New content NOT found for', selector);
    }
  });
  
  // Handle scroll-loader element separately (it might be outside the main container)
  const scrollLoaderNew = tempDiv.querySelector('#scroll-loader');
  console.log('[SPA Router] ReplaceContent: scrollLoaderNew found:', !!scrollLoaderNew);
  if (scrollLoaderNew) {
    const scrollLoaderOld = document.getElementById('scroll-loader');
    console.log('[SPA Router] ReplaceContent: scrollLoaderOld found:', !!scrollLoaderOld);
    if (scrollLoaderOld) {
      console.log('[SPA Router] ReplaceContent: Replacing scroll-loader element');
      scrollLoaderOld.innerHTML = scrollLoaderNew.innerHTML;
    } else {
      console.log('[SPA Router] ReplaceContent: Adding scroll-loader element');
      document.body.appendChild(scrollLoaderNew.cloneNode(true));
    }
  } else {
    console.log('[SPA Router] ReplaceContent: No scroll-loader element found in new content');
  }
  
  console.log('[SPA Router] ReplaceContent: Calling reinitializeDynamicContent');
  
  // Re-initialize dynamic content (event listeners, etc.)
  reinitializeDynamicContent();
}

// ============================================
// EVENT LISTENERS & REINITIALIZATION
// ============================================

/**
 * Fallback scroll to element function
 * Used when theme.js scrollToItem is not available
 * @param {string} elementId - The element ID to scroll to
 */
function spaScrollToItem(elementId) {
  const element = document.getElementById(elementId);
  if (element) {
    console.log('[SPA Router] Scrolling to element with fallback function:', elementId);
    const headerOffset = 100;
    const elementPosition = element.getBoundingClientRect().top + window.pageYOffset - headerOffset;
    window.scrollTo({
      top: elementPosition,
      behavior: 'smooth'
    });
    // Highlight the element briefly
    element.style.backgroundColor = '#7e763a';
    setTimeout(() => {
      element.style.backgroundColor = '';
    }, 2000);
  } else {
    console.warn('[SPA Router] Element not found:', elementId);
  }
}

/**
 * Scroll to item with GUID on display pages
 * This handles auto-scroll for /display/{guid} URLs
 */
function scrollToDisplayGuid() {
  // Use the stored final URL if available (for redirects), otherwise use window.location.pathname
  const effectivePath = lastFinalUrl ? new URL(lastFinalUrl).pathname : window.location.pathname;
  console.log('[SPA Router] scrollToDisplayGuid: effectivePath:', effectivePath);
  
  if (effectivePath.includes('/display/')) {
    const pathParts = effectivePath.split('/');
    console.log('[SPA Router] scrollToDisplayGuid: pathParts:', pathParts);
    // Find the display path part: /display/{guid} or /display/{guid}/...
    // The GUID is the part right after /display/
    const displayIndex = pathParts.indexOf('display');
    console.log('[SPA Router] scrollToDisplayGuid: displayIndex:', displayIndex);
    
    if (displayIndex >= 0 && displayIndex + 1 < pathParts.length) {
      const itemGuid = pathParts[displayIndex + 1];
      console.log('[SPA Router] scrollToDisplayGuid: itemGuid:', itemGuid);
      
      if (itemGuid) {
        const elementId = 'item-' + itemGuid;
        console.log('[SPA Router] scrollToDisplayGuid: elementId:', elementId, 'scrollToItem exists:', typeof scrollToItem === 'function');
        
        // Use setTimeout to allow DOM to settle after content replacement
        setTimeout(() => {
          const element = document.getElementById(elementId);
          console.log('[SPA Router] scrollToDisplayGuid: element found:', !!element);
          
          if (element) {
            console.log('[SPA Router] scrollToDisplayGuid: scrolling to element');
            if (typeof scrollToItem === 'function') {
              scrollToItem(elementId);
            } else {
              // Fallback if theme.js scrollToItem is not available
              spaScrollToItem(elementId);
            }
          } else {
            console.warn('[SPA Router] scrollToDisplayGuid: element not found!');
          }
        }, 100);
      } else {
        console.log('[SPA Router] scrollToDisplayGuid: No GUID found in path');
      }
    }
  } else {
    console.log('[SPA Router] scrollToDisplayGuid: Not a display path');
  }
  
  // Reset the lastFinalUrl after processing
  lastFinalUrl = null;
}

/**
 * Re-initialize dynamic content after SPA navigation
 * This is important for elements that need event listeners
 */
function reinitializeDynamicContent() {
  // Re-attach click handlers to new links
  // Convert NodeList to Array for compatibility with older browsers
  const links = document.querySelectorAll('a');
  Array.prototype.slice.call(links).forEach(link => {
    // Links already have the handler from event delegation, but
    // we need to ensure any new dynamic content works
  });
  
  // Scroll to item with GUID on display pages
  scrollToDisplayGuid();
  
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
    console.log('[SPA Router] Calling NavUpdate after navigation');
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
  console.log('[SPA Router] PopState: state=', e.state);
  if (e.state && e.state.spa) {
    console.log('[SPA Router] PopState: SPA navigation detected, loading', window.location.href);
    
    // Dispatch event to pause live updates before popstate navigation
    const beforeEvent = new CustomEvent('spa:beforeNavigate', {
      detail: { path: window.location.pathname, url: window.location.href }
    });
    window.dispatchEvent(beforeEvent);
    
    showLoading();
    loadContent(window.location.href);
  } else {
    console.log('[SPA Router] PopState: Not an SPA state, ignoring');
  }
}

// ============================================
// MAIN INITIALIZATION
// ============================================

/**
 * Initialize SPA Router
 */
function initSPARouter() {
  console.log('[SPA Router] Initializing... supportsSPA=', supportsSPA, 'enabled=', SPA_CONFIG.enabled);
  
  if (!supportsSPA || !SPA_CONFIG.enabled) {
    console.log('[SPA Router] Not supported or disabled');
    return;
  }
  
  // Initial load handler
  window.addEventListener('DOMContentLoaded', handleInitialLoad);
  
  // Intercept link clicks (using event delegation)
  document.addEventListener('click', handleLinkClick);
  
  // Browser navigation (back/forward)
  window.addEventListener('popstate', handlePopState);
  
  console.log('[SPA Router] Initialized successfully');
}

// ============================================
// FEATURE DETECTION & FALLBACK
// ============================================

// Check if browser supports required features
if (!supportsSPA) {
  console.log('[SPA Router] Browser does not support SPA features. Using fallback.');
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
