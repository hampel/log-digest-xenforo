# Testing Log Digest

What this add-on touches, what breaks quietly, what the suite settles, and what still needs a
person. Read it before a release.

## Surfaces

| surface | what | why |
|---|---|---|
| class extension | `XF\Admin\Controller\Tools` | adds `actionTestLogDigest` and `actionResetLogDigest`, both behind the `option` admin permission |
| listener, `app_setup` | `Listener::appSetup` | registers the `logDigest` and `logDigest.log` sub-containers |
| listener, `app_admin_setup` | `Listener::appAdminSetup` | registers the `logdigest.test` factory used by the test tool |
| listener, `hampel_admin_api_redaction` | `Listener::adminApiRedaction` | declares that none of the add-on's settings is a secret; the event exists only where an add-on defines it, so on most forums this never runs |
| cron entry | `logdigestSendLogs`, every five minutes | calls `SendLogs::serverError()`, which sends every log type |
| commands | `log-digest:config`, `log-digest:validate` | report the settings in use, and check that digests can be sent; `validate` sends a test digest unless given `--unattended` |
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
- `SecretsDeclarationTest` — the secrets declaration names only this add-on, and no option or
  option member that reads like a credential is left undeclared.
- `LogRepoTest` — `AbstractDigest`'s frequency window, fetch and de-duplication.
- `SendDigestTest` — the send pipeline with mail and the simple cache faked: a digest is emailed
  with the logs in its rendered body and last checked advances; a failed send leaves last checked
  alone; a disabled type, a type inside its frequency and a type with no new logs send nothing;
  reset; the cron entry; the fallback to the board's contact address; a disabled type is logged
  only at `debug`, and no log line carries the recipient's address.
- `ToolsControllerTest` — both tools refuse an admin without the `option` permission and render
  for one with it, with no template errors or unresolved phrases; the test tool sends a test
  digest without moving the real window, rejects a bad address and reports an unknown test; reset
  clears only the type chosen.
- `CommandClassesTest` — both command classes load as XenForo commands, neither extends a base
  class XenForo 2.2 lacks or redefines `run()`, nothing writes a grey colour tag directly, and
  every server error log call carries the add-on's prefix.
- `CommandsTest` — `config` prints the settings and changes nothing; `validate` gives the right
  exit code for clean, warning and failure with and without `--strict`; `--unattended` sends
  nothing; a test digest is sent without moving last checked, and one that cannot be sent fails
  the run; entries waiting long after the last digest fail it; a disabled or overdue cron entry,
  a bad time zone and a check that throws are each reported.
- `EmailEscapingTest` — usernames, messages, file names, traces, request URLs, request state and
  the board title all arrive escaped in both digests.

Each of these has been checked against a deliberately broken add-on, so it is known to be able to
fail: the failed-send test against the code before the fix, the permission tests with the guard
removed, the escaping test against the template that dumped request state with `dump_simple()`,
the logging tests against the code that logged a disabled type at `info` and put the recipient in
context, the secrets test with an undeclared credential-like option and option member added, and
the command tests with a stuck digest reported as fine, the error log prefix dropped, and
`--unattended` no longer skipping the send.

### On a disposable forum

These need a forum that can be thrown away, because they write to the server error log, stop the
mail server, or install a release zip. Each runs the digest from the command line, which is how a
forum running its jobs from a system cron runs it. Run them before a release, against the zip.

- **A realistic error log.** With `Hampel/Seeder` installed, `seeder:run ErrorLog -p errors=500`
  writes a log with recurring faults. Work out from the table what the digest should hold — the
  newest 200 entries, duplicates collapsed on the comparison fields, stopping at the limit — then
  run the cron and compare the log ids linked in the email. Last run on XenForo 2.3.13: the same
  ten ids in the same order, five duplicates, no markup inside any `<pre>` block. Clean the batch
  afterwards and check the log is back to what it held before.
- **A mail failure and its recovery.** Reset a log type, stop the mail server, run the cron: the
  send fails, XenForo logs the failure, and last checked does not move. Start the mail server and
  run the cron again: the held entries arrive, with the failure entry first, and last checked
  advances.
- **The upgrade from the last published release.** Install that release's zip, set a last-checked
  value, upgrade to the new zip, and run the job queue. The upgrade must report
  `Importing add-on data`; afterwards the pages render, the error log is empty, the phrase count
  matches `_output/phrases/`, and the last-checked value is unchanged. There are no upgrade steps.
- **XenForo 2.2, on the oldest PHP available.** Install the zip, run `php -l` over every file under
  the add-on's directory, save two identical error log entries and one whose request state holds
  markup, and run the cron. Last run on XenForo 2.2.19 with PHP 7.4.33: every file passed, and the
  digest held two entries and one duplicate with the markup escaped.

**The admin log digest has no equivalent of the first check.** It shares all its logic with the
server error digest and has only been run against a single hand-built entry.

## Needs a human

- **The email in a real mail client** — its layout, and that the links open the right log in the
  control panel of the right board.
- **The tool pages as a page** — the navigation entries under Tools, and the page around the
  template. The suite renders the template, not the page.
- **The test tool's "generate a test exception" option** writes a real entry to the server error
  log, so it is left out of the suite.
