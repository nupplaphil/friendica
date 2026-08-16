/**
 * The pages the smoke suite walks.
 *
 * This is deliberately the *only* place that names concrete URLs. Keep the list
 * short and made of routes that exist for every logged-in account, so a new
 * module or a renamed template never breaks the suite.
 */
export const SMOKE_ROUTES = [
	"/network",
	"/contact",
	"/message",
	"/calendar",
	"/notifications/system",
	"/settings",
	"/settings/display",
	"/settings/account",
	"/apps",
];

/**
 * Routes the SPA specs navigate between.
 *
 * Two cheap pages with different modules are enough to expose state that leaks
 * across a swap; adding more only makes the suite slower.
 */
export const SPA_ROUTES = ["/settings/display", "/network"];
