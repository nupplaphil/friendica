import { expect, test } from "@playwright/test";

import {
	BASE_URL,
	countDelegatedHandlers,
	skipUnlessSpa,
	requireSpaRouter,
	spaNavigate,
} from "../helpers/friendica.mjs";
import { SPA_ROUTES } from "../routes.mjs";

/**
 * B6 - initialisers must be idempotent across SPA navigations.
 *
 * `onDocumentReady()` handlers are re-invoked on every `spa:document:ready`, but
 * most of them bind delegated handlers to `document` / `body` - nodes that survive
 * the swap. Without a matching `.off()` those bindings accumulate, so a single
 * click ends up firing the same handler once per navigation.
 *
 * Affected today: main.js (lines 423, 442, 679, 698), frio theme.js initTheme(),
 * hovercard.js, mod_circle.js, mod_contacts.js and mod_events.js. compose.js is
 * the only one that already guards itself with `.off(".compose")`.
 */
test.describe("B6 - initialiser idempotency", () => {
	// Two round trips: enough for accumulation to show, cheap enough to stay fast.
	const ROUTES = [...SPA_ROUTES, ...SPA_ROUTES];

	test.beforeEach(async ({ page }) => {
		await page.goto(`${BASE_URL}/network`);
		await page.waitForLoadState("networkidle");
		await skipUnlessSpa(page);
		await requireSpaRouter(page);
	});

	test("delegated handlers on document and body do not accumulate", async ({ page }) => {
		const before = await countDelegatedHandlers(page);
		for (const route of ROUTES) {
			await spaNavigate(page, route);
		}
		const after = await countDelegatedHandlers(page);

		expect(
			after.total,
			`Handlers bound to document/body grew from ${before.total} to ${after.total} over ` +
			`${ROUTES.length} navigations (body.click ${before.body.click ?? 0} -> ` +
			`${after.body.click ?? 0}, document.keydown ${before.document.keydown ?? 0} -> ` +
			`${after.document.keydown ?? 0}). Every re-run of an initialiser binds another copy.`,
		).toBe(before.total);
	});

	test("a single click fires a delegated handler exactly once", async ({ page }) => {
		const probe = () => page.evaluate(async () => {
			document.getElementById("e2e-formatting-probe")?.remove();

			const button = document.createElement("button");
			button.id = "e2e-formatting-probe";
			button.setAttribute("data-role", "insert-formatting");
			button.setAttribute("data-bbcode", "b");
			button.setAttribute("data-id", "e2e");
			document.querySelector("main").appendChild(button);

			let calls = 0;
			const original = window.insertFormatting;
			window.insertFormatting = function () { calls += 1; return false; };

			button.click();
			await new Promise((resolve) => setTimeout(resolve, 250));

			window.insertFormatting = original;
			return calls;
		});

		const initial = await probe();
		expect(initial, "sanity: on a freshly loaded page the handler fires once").toBe(1);

		for (const route of ROUTES) {
			await spaNavigate(page, route);
		}
		const afterNavigations = await probe();

		expect(
			afterNavigations,
			`One click invoked insertFormatting() ${afterNavigations} times after ` +
			`${ROUTES.length} navigations. Users get their BBCode tag inserted once per navigation ` +
			"they made since loading the page.",
		).toBe(1);
	});
});
