import { defineConfig, devices } from "@playwright/test";

import { BASE_URL, STORAGE_STATE } from "./helpers/friendica.mjs";

/**
 * The suite drives a running Friendica instance, so there is no webServer block
 * here - bring the instance up yourself (see tests/e2e/README.md) and point
 * FRIENDICA_BASE_URL at it.
 */
export default defineConfig({
	testDir: ".",
	testMatch: "**/*.spec.mjs",

	// Authenticate once instead of per test; the specs are about SPA behaviour.
	globalSetup: "./global-setup.mjs",

	// SPA swaps are asynchronous and deliberately not instrumented, so the specs
	// wait on fixed settle times. Keep the per-test budget generous enough.
	timeout: 60_000,
	expect: { timeout: 10_000 },

	// Several specs assert on globals that accumulate inside one browser context,
	// so they must not run against the same instance concurrently.
	workers: 1,
	fullyParallel: false,

	forbidOnly: !!process.env.CI,
	retries: 0,
	reporter: process.env.CI ? [["list"], ["github"]] : [["list"]],

	use: {
		baseURL: BASE_URL,
		storageState: STORAGE_STATE,
		trace: "retain-on-failure",
		screenshot: "only-on-failure",
	},

	projects: [
		{ name: "chromium", use: { ...devices["Desktop Chrome"] } },
	],
});
