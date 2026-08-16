import { expect, test } from "@playwright/test";

import { BASE_URL, skipUnlessSpa, requireSpaRouter } from "../helpers/friendica.mjs";

/**
 * B1 / B2 - view/js/spa/spa-script-runtime.js
 *
 * Inline scripts of a freshly loaded page are re-executed after an SPA swap. That
 * re-execution has to preserve plain JavaScript semantics, and a failure in one
 * script must not take down the others.
 *
 * The specs use identifiers that exist nowhere else in Friendica, so a previous
 * test (or the page itself) cannot accidentally satisfy them.
 */
test.describe("B1/B2 - inline script re-execution", () => {
	test.beforeEach(async ({ page }) => {
		await page.goto(`${BASE_URL}/network`);
		await page.waitForLoadState("networkidle");
		await skipUnlessSpa(page);
		await requireSpaRouter(page);
	});

	test("B1 - function declarations stay hoisted within a script", async ({ page }) => {
		const result = await page.evaluate(async () => {
			const runtime = await import("/view/js/spa/spa-script-runtime.js");
			const source = "e2eHoistOne(); function e2eHoistOne(){ window.__e2eHoistOne = true; }";

			window.__e2eHoistOne = false;
			runtime.executeScripts([source], "e2e");

			return { ran: window.__e2eHoistOne === true, promoted: runtime.promoteToGlobal(source) };
		});

		expect(
			result.ran,
			"A hoisted function declaration is no longer callable before its definition. " +
			`promoteToGlobal() rewrote it to: ${result.promoted}`,
		).toBe(true);
	});

	test("B1 - a script may call a function declared by a later script of the same page", async ({ page }) => {
		const ran = await page.evaluate(async () => {
			const runtime = await import("/view/js/spa/spa-script-runtime.js");

			window.__e2eHoistTwo = false;
			runtime.executeScripts([
				"e2eHoistTwo();",
				"function e2eHoistTwo(){ window.__e2eHoistTwo = true; }",
			], "e2e");

			return window.__e2eHoistTwo === true;
		});

		expect(
			ran,
			"executeScripts() concatenates all scripts of a category into one block, so ordering " +
			"between them matters - but promoting declarations to assignments removes the hoisting " +
			"that made the original markup work.",
		).toBe(true);
	});

	test("B2 - a throwing script does not abort the scripts after it", async ({ page }) => {
		const result = await page.evaluate(async () => {
			const runtime = await import("/view/js/spa/spa-script-runtime.js");

			window.__e2eBatch = [];
			runtime.executeScripts([
				"window.__e2eBatch.push('first');",
				"window.__e2eBatch.push('second'); e2eDoesNotExist();",
				"window.__e2eBatch.push('third');",
			], "e2e");

			return window.__e2eBatch;
		});

		expect(
			result,
			"One failing inline script silently swallowed every script queued behind it.",
		).toContain("third");
	});

	test("B2 - script failures are reported instead of swallowed", async ({ page }) => {
		const consoleErrors = [];
		page.on("console", (message) => {
			if (message.type() === "error") {
				consoleErrors.push(message.text());
			}
		});

		await page.evaluate(async () => {
			const runtime = await import("/view/js/spa/spa-script-runtime.js");
			runtime.executeScripts(["e2eAlsoDoesNotExist();"], "e2e");
		});
		await page.waitForTimeout(250);

		expect(
			consoleErrors.join("\n"),
			"executeScripts() wraps everything in an empty catch block, so a broken inline script " +
			"leaves no trace at all - neither on window.onerror nor in the console.",
		).toMatch(/e2eAlsoDoesNotExist|is not defined/);
	});

	test("B1 - variable promotion does not duplicate mixed declarators", async ({ page }) => {
		const promoted = await page.evaluate(async () => {
			const runtime = await import("/view/js/spa/spa-script-runtime.js");
			return runtime.promoteToGlobal("var e2eA = sideEffect(), { e2eB } = source;");
		});

		expect(
			(promoted.match(/sideEffect\(\)/g) || []).length,
			`The initialiser is emitted twice, so its side effects run twice: ${promoted}`,
		).toBe(1);
	});

	test("B1 - an initialiser-less var does not clobber an existing value", async ({ page }) => {
		const value = await page.evaluate(async () => {
			const runtime = await import("/view/js/spa/spa-script-runtime.js");

			window.e2eKeepMe = "previous value";
			runtime.executeScripts(["var e2eKeepMe;"], "e2e");

			return window.e2eKeepMe;
		});

		expect(
			value,
			"`var x;` without an initialiser must not reset an existing binding, but the promotion " +
			"rewrites it to `window.x = undefined`.",
		).toBe("previous value");
	});
});
