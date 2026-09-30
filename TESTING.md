# Testing Log Digest

What this add-on touches, what breaks quietly, what the suite settles, and what still needs a
person. Read it before a release.

## Surfaces

| surface | what | why |
|---|---|---|
| class extension | `XF\Admin\Controller\Tools` | adds `actionTestLogDigest` and `actionResetLogDigest`, both behind the `option` admin permission |
| listener, `app_setup` | `Listener::appSetup` | registers the `logDigest` and `logDigest.log` sub-containers |
| listener, `app_admin_setup` | `Listener::appAdminSetup` | registers the `logdigest.test` factory used by the test tool |
| cron entry | `logdigestSendLogs`, every five minutes | calls `SendLogs::serverError()`, which sends every log type |
| options | `logdigestEmail`, `logdigestTimeZone`, `logdigestServerError`, `logdigestAdminLog` | recipient, date display, and per-type enabled/frequency/limit/deduplicate |
| admin navigation | `testLogDigest`, `resetLogDigest` | the two tools, under Tools |
| templates | three email, four admin | the digests and the tool pages |
| simple cache | set `Hampel/LogDigest`, keyed by entity (`XF:ErrorLog`, `XF:AdminLog`) | the last-checked time per log type — the add-on's only state |

## Fragile points

- **After a send, last checked is set to the current time, not to the newest log's.** Only the
  most recent `limit` entries are emailed and anything older in the same window is skipped. That
  is the intended behaviour since 3.1.0, and it means a burst larger than the limit is summarised,
  not delivered in full.
- **A failed send leaves last checked alone, so the same logs are retried every five minutes.**
  With mail broken for good, each run retries and XenForo logs each failure as a new error entry,
  which then joins the retry.
- **Logging goes to a `logdigest` Monolog channel only when another add-on has registered
  `monolog` on the container.** Without one, every log call is a no-op by design.
- **`XF\Admin\Controller\Tools` is a commonly extended controller**, so on a real forum this
  add-on is usually one link in a chain of extensions.
- **The links in the email are canonical admin URLs**, so they are only as right as the board URL
  configured in the options.

## Automated

```bash
composer install
vendor/bin/phpunit
```

The suite boots XenForo with only this add-on loaded (`$addonsToLoad`), so it needs the
surrounding install and its database. What it settles:

- `DigestCacheRepoTest` — the last-checked store, including the pre-3.1 array shape.
- `LogRepoTest` — `AbstractDigest`'s frequency window, fetch and de-duplication.
- `SendDigestTest` — the send pipeline with mail and the simple cache faked: a digest is emailed
  with the logs in its rendered body and last checked advances; a failed send leaves last checked
  alone; a disabled type, a type inside its frequency and a type with no new logs send nothing;
  reset; the cron entry; the fallback to the board's contact address.
- `ToolsControllerTest` — both tools refuse an admin without the `option` permission and render
  for one with it, with no template errors or unresolved phrases; the test tool sends a test
  digest without moving the real window, rejects a bad address and reports an unknown test; reset
  clears only the type chosen.

Two of those have been checked against a deliberately broken add-on: the failed-send test fails
against the code before the fix, and the permission tests fail with the guard removed.

## Needs a human

- **The email in a real mail client** — its layout, and that the links open the right log in the
  control panel of the right board.
- **A real mail failure and its recovery**, end to end: the suite fakes the transport, so that
  the next cron run after the mail server comes back delivers the held logs is shown only in part.
- **The upgrade from the last published release**, on a forum installed from zips rather than a
  development checkout. There are no upgrade steps, so the risk is low; on XenForo 2.3 the upgrade
  queues XenForo's post-upgrade file clean-up.
- **XenForo 2.2.** The declared floor is 2.2.0, and the suite runs against whichever install it
  is in. Every core call has been checked against 2.2 source; an install and a cron run on 2.2
  has not been re-done for this release.
- **The tool pages as a page** — the navigation entries under Tools, and the page around the
  template. The suite renders the template, not the page.
- **The test tool's "generate a test exception" option** writes a real entry to the server error
  log, so it is left out of the suite.
