import { expect, test } from "@playwright/test";

import { BASE_URL, skipUnlessSpa, requireSpaRouter, spaNavigate } from "../helpers/friendica.mjs";

/**
 * B3 - server-provided globals must survive an SPA navigation.
 *
 * `head.tpl` declares `spaEnabled`, `localUser` and `updateContent` with `const`.
 * A top-level `const` lives in the global declarative record, not on `window`, so
 * the `window.x = ...` assignments that promoteToGlobal() emits during a swap are
 * permanently shadowed by the binding from the initial page load.
 *
 * Effect: whatever the server sent on the very first full page load is frozen for
 * the rest of the session.
 */
test.describe("B3 - server-provided globals", () => {
	test.beforeEach(async ({ page }) => {
		await page.goto(`${BASE_URL}/network`);
		await page.waitForLoadState("networkidle");
		await skipUnlessSpa(page);
		await requireSpaRouter(page);
	});

	for (const name of ["spaEnabled", "localUser", "updateContent"]) {
		test(`${name} reflects the value delivered by the current page`, async ({ page }) => {
			const result = await page.evaluate(async (globalName) => {
				const runtime = await import("/view/js/spa/spa-script-runtime.mjs");

				// Exactly what head.tpl ships on the next page, with a changed value.
				runtime.executeScripts([`const ${globalName} = 4242;`], "e2e");

				return {
					read: window.eval(globalName),
					onWindow: window[globalName],
					hasOwn: Object.prototype.hasOwnProperty.call(window, globalName),
				};
			}, name);

			expect(result.hasOwn, `window.${name} should have been written`).toBe(true);
			expect(
				result.read,
				`Reads of \`${name}\` still resolve to the const from the initial page load ` +
				`(${result.read}) instead of the value the new page delivered (${result.onWindow}). ` +
				"Declaring these as `const` in head.tpl makes them unupdatable for the whole SPA session.",
			).toBe(4242);
		});
	}

	test("a value changed by the server reaches the running page after navigation", async ({ page }) => {
		const before = await page.evaluate(() => window.eval("localUser"));

		// Serve the next page with a different value, exactly as the server would
		// after a delegation switch or a settings change.
		await page.route("**/settings/display*", async (route) => {
			const response = await route.fetch();
			const body = (await response.text()).replace(/const localUser = [^;]+;/, "const localUser = 9999;");
			await route.fulfill({ response, body });
		});

		await spaNavigate(page, "/settings/display");

		const after = await page.evaluate(() => ({
			path: window.location.pathname,
			read: window.eval("localUser"),
			onWindow: window.localUser,
		}));

		expect(after.path, "sanity: the navigation happened").toBe("/settings/display");
		expect(
			after.read,
			`The page still reads localUser === ${after.read} (it was ${before} on load), although ` +
			`the response declared 9999 and window.localUser is ${after.onWindow}. The const binding ` +
			"from the initial load shadows every later update for the rest of the session.",
		).toBe(9999);
	});
});
