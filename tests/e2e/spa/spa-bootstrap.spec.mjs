import { expect, test } from "@playwright/test";

import { BASE_URL, skipUnlessSpa } from "../helpers/friendica.mjs";

/**
 * B0 - SPA bootstrap must not be a single point of failure.
 *
 * `head.tpl` emits `const spaEnabled = 1` from server-side config, and `main.js`
 * uses that flag to decide whether `onDocumentReady()` binds via jQuery or defers
 * to the `spa:document:ready` event. If the router module graph never loads, that
 * event is never dispatched and *no* registered initialiser runs at all.
 *
 * The flag therefore has to describe what the client actually managed to load,
 * not merely what the server intended.
 */
test.describe("B0 - SPA bootstrap", () => {
	test("the SPA module graph loads without MIME errors", async ({ page }) => {
		const moduleErrors = [];
		page.on("console", (message) => {
			if (message.type() === "error" && /module script|MIME type/i.test(message.text())) {
				moduleErrors.push(message.text());
			}
		});

		await page.goto(`${BASE_URL}/network`);
		await page.waitForLoadState("networkidle");
		await skipUnlessSpa(page);

		expect(
			moduleErrors,
			"The browser refused to execute part of the SPA module graph. The most common cause is a " +
			"web server that does not map .mjs to a JavaScript MIME type (stock nginx mime.types has " +
			"no .mjs entry before 1.27.4), which makes /view/asset/acorn/dist/acorn.mjs load as " +
			"application/octet-stream.",
		).toEqual([]);
	});

	test("page initialisers run even when the SPA router fails to load", async ({ page }) => {
		// Simulate any failure of the router module graph - a MIME misconfiguration,
		// a 404 after a partial deploy, a CSP rule. The page must still come up.
		await page.route("**/view/js/spa/spa-router.*js*", (route) => route.abort());

		await page.goto(`${BASE_URL}/network`);
		await page.waitForLoadState("networkidle");
		await skipUnlessSpa(page);
		await page.waitForTimeout(1000);

		const state = await page.evaluate(() => ({
			ajaxSetupApplied: !!(window.$ && window.$.ajaxSettings && window.$.ajaxSettings.cache === false),
			delegatedHandlers: (window.$._data(document.body, "events")?.click || []).length,
		}));

		expect(
			state.ajaxSetupApplied,
			"The main.js init block never ran, so the page has no autocomplete, no editor and no " +
			"theme init. Registering a page initialiser must not depend on the SPA module graph " +
			"being loadable.",
		).toBe(true);
		expect(state.delegatedHandlers, "no delegated handler was bound at all").toBeGreaterThan(0);
	});
});
