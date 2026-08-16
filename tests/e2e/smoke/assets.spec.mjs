import { expect, test } from "@playwright/test";

import { BASE_URL } from "../helpers/friendica.mjs";

/**
 * Everything a page pulls in from our own host has to be deliverable and, for
 * scripts, executable.
 *
 * Two classes of regression are invisible in the markup and cost hours to track
 * down otherwise: an asset that 404s after a move, and a script the browser
 * refuses to run because the server hands it the wrong MIME type (stock nginx,
 * for instance, has no `.mjs` mapping before 1.27.4).
 */
test.describe("smoke - assets", () => {
	test("no same-origin asset fails to load", async ({ page }) => {
		const failures = [];

		page.on("response", (response) => {
			const url = response.url();
			if (url.startsWith(BASE_URL) && response.status() >= 400) {
				failures.push(`${response.status()} ${url}`);
			}
		});
		page.on("requestfailed", (request) => {
			if (request.url().startsWith(BASE_URL)) {
				failures.push(`${request.failure()?.errorText ?? "failed"} ${request.url()}`);
			}
		});

		await page.goto(`${BASE_URL}/network`);
		await page.waitForLoadState("networkidle");

		expect(failures, "assets referenced by /network could not be loaded").toEqual([]);
	});

	test("no script is rejected for its MIME type", async ({ page }) => {
		const mimeErrors = [];
		page.on("console", (message) => {
			if (message.type() === "error" && /MIME type|strict MIME|module script/i.test(message.text())) {
				mimeErrors.push(message.text());
			}
		});

		await page.goto(`${BASE_URL}/network`);
		await page.waitForLoadState("networkidle");

		expect(
			mimeErrors,
			"The browser refused to execute a script because the server sent the wrong Content-Type.",
		).toEqual([]);
	});
});
