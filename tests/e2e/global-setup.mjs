import { chromium } from "@playwright/test";

import { STORAGE_STATE, login } from "./helpers/friendica.mjs";

/**
 * Log in once and hand the session to every spec via `use.storageState`.
 *
 * Logging in per test was measurably flaky against a real instance, and it told
 * us nothing the login itself does not - the specs are about application
 * behaviour, not about authentication.
 */
export default async function globalSetup() {
	const browser = await chromium.launch();
	try {
		const page = await browser.newPage();
		await login(page);
		await page.context().storageState({ path: STORAGE_STATE });
	} finally {
		await browser.close();
	}
}
