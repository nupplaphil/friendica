// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

// @license magnet:?xt=urn:btih:0b31508aeb0634b347b8270c7bee4d411b5d4109&dn=agpl-3.0.txt AGPLv3-or-later

function createContentLoader(options) {
  const {
    showTimeoutModal,
    replaceContainerContent
  } = options;

  /**
   * Handle HTTP error responses.
   * @param {number} status - HTTP status code
   * @param {string} url - The original request URL
   */
  function handleHttpError(status, url) {
    console.debug('[SPA Router] handleHttpError: status=', status, 'url=', url);

    if (status === 401) {
      console.debug('[SPA Router] handleHttpError: 401 Unauthorized - redirecting to login');
      window.location.href = '/login?return_path=' + encodeURIComponent(url);
    }
  }

  /**
   * Load content via AJAX.
   * @param {string} url - The URL to load
   */
  function loadContent(url) {
    console.debug('[SPA Router] LoadContent: url=', url);

    const fetchUrl = new URL(url, window.location.href);
    console.debug('[SPA Router] LoadContent: fetching URL=', fetchUrl.toString());

    let finalUrl = fetchUrl.toString();

    showFetching();
    fetch(fetchUrl, { headers: { 'Accept': 'text/html' }, credentials: 'include' })
      .then(async (response) => {

        console.debug('[SPA Router] LoadContent: Response received, status=', response.status, 'response.url=', response.url);

        const contentType = response.headers.get('Content-Type') || response.headers.get('content-type') || '';
        if (!contentType.includes('text/html')) {
          console.debug('[SPA Router] LoadContent: Invalid Content-Type:', contentType, '- falling back to full reload');
          window.location.href = url;
          return null;
        }

        if (response.url && response.url !== fetchUrl.toString()) {
          finalUrl = response.url;
          console.debug('[SPA Router] LoadContent: Final URL after redirects:', finalUrl);
        }

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

        showReceiving();
        const html = await response.text();

        console.debug('[SPA Router] LoadContent: HTML received, type:', typeof html, 'length:', html ? html.length : 0, 'finalUrl:', finalUrl);

        showProcessing();

        if (typeof html !== 'string') {
          console.error('[SPA Router] LoadContent: html is not a string! type:', typeof html, 'value:', html);
          throw new Error('Response body is not a string');
        }

        if (finalUrl !== fetchUrl.toString()) {
          console.debug('[SPA Router] LoadContent: Updating history to final URL:', finalUrl);
          history.replaceState({ path: new URL(finalUrl).pathname, spa: true, __friendicaSPA: true }, '', finalUrl);
        }

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

        if (error.name === 'AbortError') {
          console.debug('[SPA Router] LoadContent: AbortError (client timeout) - dismissing delay modal only');
        } else if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
          console.debug('[SPA Router] LoadContent: Network error - falling back to reload');
          window.location.href = url;
        } else {
          console.debug('[SPA Router] Falling back to full page reload for URL:', url);
          window.location.href = url;
        }
      });
  }

  return {
    handleHttpError,
    loadContent
  };
}

export { createContentLoader };