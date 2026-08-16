import { expect, test } from "@playwright/test";

import { BASE_URL, skipUnlessSpa, requireSpaRouter, routerUrlExpression } from "../helpers/friendica.mjs";

/**
 * B7 - view/js/spa/spa-ui-helpers.js cleanupTooltips()
 *
 * The selector list is meant to remove orphaned tooltip and popover nodes, but it
 * also matches `.panel` and `[class*="tooltip"]`, and it runs against the whole
 * document as the very first statement of replaceContainerContent() - before the
 * response has even been parsed.
 *
 * `.panel` is regular frio markup (contact_edit.tpl, settings/account.tpl,
 * mail_display.tpl, message_side.tpl, home.tpl), so this deletes real content.
 */
test.describe("B7 - DOM swap cleanup", () => {
	test("cleanupTooltips() leaves content panels alone", async ({ page }) => {
		await page.goto(`${BASE_URL}/settings/account`);
		await page.waitForLoadState("networkidle");
		await skipUnlessSpa(page);
		await requireSpaRouter(page);

		const result = await page.evaluate(async () => {
			const helpers = await import("/view/js/spa/spa-ui-helpers.js");
			const count = () => ({
				panels: document.querySelectorAll(".panel").length,
				forms: document.querySelectorAll("form.panel").length,
			});

			const before = count();
			helpers.cleanupTooltips();
			return { before, after: count() };
		});

		expect(result.before.panels, "sanity: the settings page uses .panel markup").toBeGreaterThan(0);
		expect(
			result.after.panels,
			`cleanupTooltips() removed ${result.before.panels - result.after.panels} .panel elements, ` +
			`among them ${result.before.forms - result.after.forms} settings forms. The selector list ` +
			"needs to be scoped to actual tooltip/popover containers.",
		).toBe(result.before.panels);
	});

	test("content survives a navigation whose response has no <main>", async ({ page }) => {
		await page.goto(`${BASE_URL}/settings/account`);
		await page.waitForLoadState("networkidle");
		await skipUnlessSpa(page);
		await requireSpaRouter(page);

		// A minimal or error response is a realistic outcome; the current page must
		// not be left blank because cleanup already ran.
		await page.route("**/settings/display*", (route) => route.fulfill({
			status: 200,
			contentType: "text/html",
			body: "<html><head><title>Minimal</title></head><body><p>no main element</p></body></html>",
		}));

		const result = await page.evaluate(async (routerExpression) => {
			const before = document.querySelectorAll(".panel").length;
			const router = await import(window.eval(routerExpression));

			router.navigateTo("/settings/display");
			await new Promise((resolve) => setTimeout(resolve, 1500));

			return { before, after: document.querySelectorAll(".panel").length };
		}, routerUrlExpression());

		expect(
			result.after,
			"The page was emptied: cleanupTooltips() deleted the panels up front, the response had " +
			"no <main> to swap in, and nothing restored them.",
		).toBe(result.before);
	});
});
