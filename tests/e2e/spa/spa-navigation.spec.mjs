import { expect, test } from "@playwright/test";

import { BASE_URL, skipUnlessSpa, requireSpaRouter, routerUrlExpression } from "../helpers/friendica.mjs";

/**
 * B4 / B5 / B8 - view/js/spa/spa-navigation-adapter.js and spa-content-loader.js
 *
 * The click interceptor sits on `document` in the bubble phase and cancels the
 * default action for every internal link. It therefore has to respect the ways
 * markup and other handlers opt out of normal navigation.
 */
test.describe("B4/B5/B8 - link interception and navigation", () => {
	test.beforeEach(async ({ page }) => {
		await page.goto(`${BASE_URL}/network`);
		await page.waitForLoadState("networkidle");
		await skipUnlessSpa(page);
		await requireSpaRouter(page);
	});

	test("B4 - an internal target=\"_blank\" link opens a new tab", async ({ context, page }) => {
		await page.evaluate(() => {
			const link = document.createElement("a");
			link.id = "e2e-blank-link";
			link.href = "/settings/display";
			link.target = "_blank";
			link.textContent = "new tab";
			document.querySelector("main").appendChild(link);
		});

		const before = page.url();
		let opened = false;
		context.on("page", () => { opened = true; });

		await page.click("#e2e-blank-link");
		await page.waitForTimeout(1500);

		expect(
			opened || page.url() === before,
			"The link was hijacked and navigated in place. handleLinkClick() checks neither " +
			"link.target nor the download attribute, which affects the plink icons in " +
			"wall_thread.tpl, search_item.tpl, shared_content.tpl and calendar/event.tpl.",
		).toBe(true);
	});

	test("B4 - a download link is left to the browser", async ({ page }) => {
		const navigated = await page.evaluate(async () => {
			const link = document.createElement("a");
			link.id = "e2e-download-link";
			link.href = "/settings/display";
			link.setAttribute("download", "export.json");
			document.querySelector("main").appendChild(link);

			const start = window.location.pathname;
			link.click();
			await new Promise((resolve) => setTimeout(resolve, 1500));

			return window.location.pathname !== start;
		});

		expect(navigated, "A link carrying `download` must not trigger an SPA navigation.").toBe(false);
	});

	test("B5 - a cancelled click does not navigate", async ({ page }) => {
		const result = await page.evaluate(async () => {
			const link = document.createElement("a");
			link.id = "e2e-cancelled-link";
			link.href = "/settings/display";
			document.querySelector("main").appendChild(link);
			link.addEventListener("click", (event) => {
				event.preventDefault();
				window.__e2eCancelled = true;
			});

			const start = window.location.pathname;
			link.click();
			await new Promise((resolve) => setTimeout(resolve, 1500));

			return { cancelled: !!window.__e2eCancelled, start, end: window.location.pathname };
		});

		expect(result.cancelled, "sanity: the inner handler ran").toBe(true);
		expect(
			result.end,
			"An inner handler cancelled the click, but the SPA navigated anyway. The interceptor " +
			"runs in the bubble phase and never looks at e.defaultPrevented, so it silently " +
			"overrides every jQuery widget that cancels a click.",
		).toBe(result.start);
	});

	test("B8 - a superseded navigation does not overwrite the newer one", async ({ page }) => {
		// Make the first target slow so the ordering is deterministic.
		await page.route("**/calendar*", async (route) => {
			await new Promise((resolve) => setTimeout(resolve, 1500));
			await route.continue();
		});

		const result = await page.evaluate(async (routerExpression) => {
			const router = await import(window.eval(routerExpression));

			router.navigateTo("/calendar");
			await new Promise((resolve) => setTimeout(resolve, 100));
			router.navigateTo("/settings/display");
			await new Promise((resolve) => setTimeout(resolve, 4000));

			return {
				path: window.location.pathname,
				content: (document.querySelector("main")?.innerText || "").slice(0, 200),
			};
		}, routerUrlExpression());

		expect(result.path).toBe("/settings/display");
		expect(
			result.content,
			`The slower first request won the race: the URL says ${result.path} but the content is ` +
			"the one of the earlier navigation. loadContent() has to abort a superseded request.",
		).toMatch(/Two-factor|Account|Display/i);
	});
});
