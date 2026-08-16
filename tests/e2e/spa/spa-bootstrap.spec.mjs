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
		await page.route("**/view/js/spa/spa-router.js*", (route) => route.abort());

		await page.goto(`${BASE_URL}/network`);
		await page.waitForLoadState("networkidle");
		await skipUnlessSpa(page);
		await page.waitForTimeout(1000);

		const state = await page.evaluate(() => ({
			registered: window.__onDocumentReadyRegistry ? window.__onDocumentReadyRegistry.size : 0,
			ajaxSetupApplied: !!(window.$ && window.$.ajaxSettings && window.$.ajaxSettings.cache === false),
		}));

		expect(state.registered, "handlers should have been registered").toBeGreaterThan(0);
		expect(
			state.ajaxSetupApplied,
			"The main.js init block never ran. With the router unavailable nothing dispatches " +
			"spa:document:ready, so every onDocumentReady() handler is stranded and the page has no " +
			"theme init, no autocomplete and no editor. onDocumentReady() needs to fall back to " +
			"$(document).ready when the router is not actually up.",
		).toBe(true);
	});
});
