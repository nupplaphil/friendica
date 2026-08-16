import { expect, test } from "@playwright/test";

import { BASE_URL } from "../helpers/friendica.mjs";
import { SMOKE_ROUTES } from "../routes.mjs";

/**
 * Every page a logged-in account can reach must come up, keep the session and
 * raise no uncaught JavaScript error.
 *
 * These are invariants, not markup assertions: nothing here knows what a page
 * looks like, only that it rendered *something* and that the client-side code on
 * it did not throw. That keeps the suite quiet while templates keep churning.
 */
test.describe("smoke - pages render", () => {
	for (const path of SMOKE_ROUTES) {
		test(`${path} renders without a client-side error`, async ({ page }) => {
			const pageErrors = [];
			page.on("pageerror", (error) => pageErrors.push(error.message));

			const response = await page.goto(`${BASE_URL}${path}`);
			expect(response.status(), `${path} did not return a successful status`).toBeLessThan(400);

			await page.waitForLoadState("networkidle");

			const state = await page.evaluate(() => {
				const root = document.querySelector("main") || document.getElementById("content") || document.body;
				return {
					authenticated: typeof localUser !== "undefined" && localUser !== false,
					contentLength: (root.innerText || "").trim().length,
				};
			});

			expect(state.authenticated, `${path} dropped the session`).toBe(true);
			expect(state.contentLength, `${path} rendered an empty content area`).toBeGreaterThan(0);
			expect(pageErrors, `${path} raised an uncaught JavaScript error`).toEqual([]);
		});
	}
});
