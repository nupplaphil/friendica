<!--
SPDX-FileCopyrightText: 2010-2024 the Friendica project

SPDX-License-Identifier: AGPL-3.0-or-later
-->

# End-to-end tests

Browser tests that drive a **running** Friendica instance with
[Playwright](https://playwright.dev/). They are not part of `composer run test`
and they need no database access of their own.

```bash
npm install
npm run test:e2e:install     # once: downloads the chromium build
npm run test:e2e             # everything
npm run test:e2e:smoke       # only the branch-agnostic smoke tests
npm run test:e2e:spa         # only the SPA specs
```

By default this talks to the local Docker stack
(`docker compose -f .docker/compose.yaml up -d`, admin/admin):

| Variable             | Default                 |
|----------------------|-------------------------|
| `FRIENDICA_BASE_URL` | `http://localhost:8080` |
| `FRIENDICA_USER`     | `admin`                 |
| `FRIENDICA_PASSWORD` | `admin`                 |

## Layout

```
tests/e2e/
├── routes.mjs          the only place that names URLs
├── helpers/            login, SPA detection, handler counting
├── smoke/              runs on every branch
└── spa/                skipped automatically when SPA mode is off
```

`global-setup.mjs` logs in once; every spec reuses that session through
`use.storageState`. No spec logs in by itself.

## Keeping this cheap to maintain

The whole point of the suite is to cost nothing between the days it catches
something. That only holds if the tests do not know what the UI looks like:

- **Assert invariants, not markup.** "the page rendered something and threw
  nothing", "handler count did not grow", "the value the server sent is the value
  the page reads". Element ids and classes in `view/` churn; invariants do not.
- **Never pin a selector** unless it is the subject of the test. The suite as a
  whole depends on exactly three pieces of markup, all of them stable:
  `#id_username` / `#id_password` on the login form, and `main` as the content
  root (with `#content` and `body` as fallbacks).
- **All URLs live in `routes.mjs`.** A renamed module is a one-line fix.
- **Feature specs skip themselves.** `skipUnlessSpa()` makes the SPA specs a
  no-op on a branch or an account without SPA mode, instead of a wall of red.
- **Nothing here gates other work.** The suite is not wired into `npm run lint`
  or the PHPUnit suites, so it can never block an unrelated change.

If a test starts failing for a reason that is not a regression, that is a bug in
the test - relax it or delete it rather than teaching it about the new markup.

## What the SPA specs cover

`spa/` was written from reproductions against the SPA branch and describes the
acceptance criteria for it, so the specs are expected to fail until those are
met:

| Spec                      | Invariant                                                            |
|---------------------------|----------------------------------------------------------------------|
| `spa-bootstrap`           | page initialisers run even when the router module fails to load       |
| `spa-script-runtime`      | re-executed inline scripts keep hoisting; one failure is isolated and reported |
| `spa-globals`             | globals the server sends reach the running page after a navigation    |
| `spa-navigation`          | `target`, `download` and `preventDefault()` are respected; the newest navigation wins |
| `spa-handlers`            | re-running an initialiser does not bind a second handler              |
| `spa-dom-swap`            | cleanup does not delete real content, and a bad response leaves the page intact |
