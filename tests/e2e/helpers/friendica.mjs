import { test } from "@playwright/test";

/**
 * Shared helpers for the end-to-end suite.
 *
 * Everything here talks to a *running* Friendica instance; see tests/e2e/README.md
 * for how to bring one up and which environment variables are honoured.
 */

export const BASE_URL = process.env.FRIENDICA_BASE_URL || "http://localhost:8080";
export const USERNAME = process.env.FRIENDICA_USER || "admin";
export const PASSWORD = process.env.FRIENDICA_PASSWORD || "admin";

/** Session captured once by global-setup.mjs and reused by every spec. */
export const STORAGE_STATE = "test-results/.friendica-session.json";

/**
 * Log in via the regular login form.
 *
 * The form carries a hidden `auth-params` field; submitting the form (rather than
 * POSTing the fields by hand) makes sure it is sent, otherwise the request is
 * silently redirected back to the front page.
 */
export async function login(page) {
	await page.goto(`${BASE_URL}/login`, { waitUntil: "domcontentloaded" });
	await page.waitForSelector("#id_username");
	await page.fill("#id_username", USERNAME);
	await page.fill("#id_password", PASSWORD);
	await Promise.all([
		page.waitForURL((url) => !url.pathname.endsWith("/login"), { timeout: 20_000 }),
		page.click("form#login-form button[type=submit], form#login-form input[type=submit]"),
	]);
	await page.waitForLoadState("networkidle");

	const loggedIn = await page.evaluate(() => typeof localUser !== "undefined" && localUser !== false);
	if (!loggedIn) {
		throw new Error(
			`Could not log in as "${USERNAME}" at ${BASE_URL}. ` +
			"Set FRIENDICA_USER / FRIENDICA_PASSWORD if your instance uses different credentials.",
		);
	}
}

/**
 * Whether the page currently loaded runs in SPA mode.
 *
 * The flag is emitted by head.tpl from the per-user setting, so a branch without
 * the SPA feature simply does not define it.
 */
export async function isSpaEnabled(page) {
	return page.evaluate(() => (typeof spaEnabled === "undefined" ? false : !!spaEnabled));
}

/**
 * Skip the current test unless the loaded page runs in SPA mode.
 *
 * The SPA specs would pass vacuously without it, and the feature is neither
 * present on every branch nor enabled for every account. Call this after the
 * first `goto()` of a test.
 */
export async function skipUnlessSpa(page) {
	test.skip(
		!(await isSpaEnabled(page)),
		`SPA mode is off for "${USERNAME}" (Settings -> Display -> "Enable SPA mode"), ` +
		"or this branch does not ship the feature.",
	);
}

/**
 * Build a browser expression that resolves an SPA module by its base name.
 *
 * The page loads the router as `spa-router.js?v=<version>`; importing a bare
 * path would be a *different* module specifier, so the browser would evaluate a
 * second, independent copy - which re-runs initSPARouter() and double-binds its
 * listeners. Resolving against the tag in the DOM also keeps the specs working
 * whichever file extension the modules currently use.
 *
 * @param {string} name module base name, e.g. "spa-dom-swap"
 */
export function moduleUrlExpression(name) {
	return `
	(function () {
		const tag = document.querySelector('script[type=module][src*="spa/spa-router."]');
		if (!tag) {
			return "/view/js/spa/${name}.js";
		}
		return tag.src.replace(/spa-router\\./, "${name}.");
	})()
	`;
}

/** The router module as the page itself loaded it. */
const ROUTER_URL_EXPRESSION = moduleUrlExpression("spa-router");

/**
 * Assert that the SPA router module graph actually loaded.
 *
 * A failure here almost always means the web server does not serve the module
 * graph with a JavaScript MIME type - see the B0 spec, which covers that case
 * explicitly. Checking it up front keeps the other specs from failing with
 * confusing follow-up errors.
 */
export async function requireSpaRouter(page) {
	const status = await page.evaluate(async (routerExpression) => {
		try {
			await import(window.eval(routerExpression));
			return "ok";
		} catch (error) {
			return String(error && error.message);
		}
	}, ROUTER_URL_EXPRESSION);

	if (status !== "ok") {
		throw new Error(
			`The SPA router module could not be loaded: ${status}\n` +
			"If this mentions a MIME type, the web server is not serving .mjs as JavaScript. " +
			"See tests/e2e/README.md.",
		);
	}
}

/**
 * Perform an in-page SPA navigation and wait for the swap to settle.
 *
 * Driving the router directly (instead of clicking a link) keeps the specs
 * independent of whichever links happen to be present on a given page.
 */
export async function spaNavigate(page, path, settleMs = 1500) {
	await page.evaluate(async ([target, routerExpression]) => {
		const router = await import(window.eval(routerExpression));
		router.navigateTo(target);
	}, [path, ROUTER_URL_EXPRESSION]);
	await page.waitForTimeout(settleMs);
}

/**
 * Import the router module the page is actually running, for specs that need to
 * call into it directly.
 */
export function routerUrlExpression() {
	return ROUTER_URL_EXPRESSION;
}

/**
 * Count the jQuery event handlers bound to `document` and `document.body`.
 *
 * These are the two nodes that survive every SPA swap, so anything bound to them
 * without a matching `.off()` accumulates across navigations.
 */
export function countDelegatedHandlers(page) {
	return page.evaluate(() => {
		const perNode = (node) => {
			const events = window.$._data(node, "events") || {};
			return Object.fromEntries(Object.entries(events).map(([name, list]) => [name, list.length]));
		};

		const body = perNode(document.body);
		const doc = perNode(document);
		const sum = (obj) => Object.values(obj).reduce((carry, value) => carry + value, 0);

		return { body, document: doc, total: sum(body) + sum(doc) };
	});
}
