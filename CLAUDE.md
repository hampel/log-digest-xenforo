# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Scope and prerequisites

This repo is one XenForo add-on (`Hampel/LogDigest`): a cron job that emails recent entries from
the forum's server error log and admin log to an administrator. It lives at
`<xf-root>/src/addons/Hampel/LogDigest`, so the install root is four levels up.

If the install has an `AGENTS.md` at its root — XenForo generates one with
`xf-dev:install-ai-agents` — read it first for that install's own conventions, along with anything
under `.agents/skills/`. Neither is part of this repository, so neither is guaranteed to be there.

`CLAUDE.local.md` beside this file, if present, holds whatever is true only of one machine:
absolute paths, other installs, working notes. It is gitignored, so it never travels.

## Commands

These all run from the add-on root:

```bash
composer install                                   # required before tests; vendor/ is gitignored

vendor/bin/phpunit                                 # all tests
vendor/bin/phpunit --testsuite Unit                # one suite (Unit | Feature)
vendor/bin/phpunit tests/Unit/DigestCacheRepoTest.php
vendor/bin/phpunit --filter test_reset_returns_zero  # a single test

# cmd.php resolves the install from its own location, so a relative path works from here
php ../../../../cmd.php xf-dev:import --addon=Hampel/LogDigest
php ../../../../cmd.php xf-addon:export Hampel/LogDigest
php ../../../../cmd.php xf-addon:build-release Hampel/LogDigest
```

**The test suite needs PHP 8.3, though the add-on itself requires XenForo 2.2 and PHP 7.4.** The
suite's floor comes from `hampel/xenforo-test-framework` 5.x and PHPUnit 12, both `require-dev` —
they are not shipped, and `composer.json` is stripped from the release, so they constrain
contributors rather than users. **Keep runtime code 7.4-compatible**: no union types, `match`,
nullsafe operators, named arguments or constructor promotion outside `tests/`.

**The PHP 7.4 requirement is set by `Cli/RendersReport.php`**, which has a typed static property.
That file is a whole copy shared with other add-ons and is not adapted here, so the requirement
cannot be lowered without giving up the two commands that use it. It is declared in three places
that have to agree: `addon.json`, `composer.json` and the README.

Tests boot a real `\XF\App` through the framework, so they need the surrounding install and its
database, not just this directory. **`$addonsToLoad` in `tests/TestCase.php` is load-bearing** — it
loads only this add-on's listeners, class extensions and vendor tree. Emptied, XenForo registers
every installed add-on's autoloader, and one that vendors a different PHPUnit major kills the run
before the first test.

- `tests/Unit/` — the last-checked store (`DigestCacheRepoTest`), `AbstractDigest`'s fetch and
  de-duplication (`LogRepoTest`), and the send pipeline end to end with mail and the simple cache
  faked (`SendDigestTest`), including a failed send leaving the window alone. `LoggerTest` covers
  where log lines go with and without a logging add-on.
- `tests/Feature/` — the two ACP tools (`ToolsControllerTest`): the `option` permission guard
  through `dispatch()`, and the test and reset actions through `callAction()`.
- `tests/Feature/EmailEscapingTest` — every visitor-controlled field in both email templates
  arrives escaped. Tests run under the CLI, which is where a cron-driven digest runs too, so this is
  the case that matters: `dump_simple()` escapes nothing there.

A failed send is simulated with `$this->fakesMail()->failWith(...)` (framework 5.14.0 and later),
paired with `fakesErrors()` so the mailer's log entry stays out of the forum's real error log.

## Architecture

### One cron entry drives every log type

`Cron/SendLogs::serverError()` runs every five minutes (`logdigestSendLogs`). **Despite its name
it sends every log type**, not only server errors: it calls `sendAll()` on the `logDigest`
sub-container. The method name is stored in the cron entry in `_output/`, so renaming it means
changing both.

### Services live in sub-containers registered by `Listener`

`Listener::appSetup` registers two sub-containers on the app container:

- **`logDigest`** (`SubContainer/LogDigest`) — the orchestrator. Its `digest.list` entry maps a
  short key (`server_error_log`, `admin_log`) to a digest class, and its `digest` factory builds
  one via `extendClass`, so each digest is itself extensible by other add-ons. `send()` checks the
  type is enabled, fetches, prepares, mails and then advances the last-checked time.
- **`logDigest.log`** (`SubContainer/Log`) — a PSR-3-shaped wrapper that writes to a `logdigest`
  Monolog channel **only if some other add-on has registered `monolog` on the container**;
  otherwise its logger is a `Psr\Log\NullLogger`, so it is never `null` and nothing checks for
  one. `Helper/Log` is the static facade the rest of the code calls.
  **The channel is asked for with `channel()` where the logging add-on has it and `newChannel()`
  where it does not** — the first is that add-on's 5.x name, the second its 4.x one, and a forum may
  run either. Keep the channel name: a forum's own configuration can target it. `LoggerTest`
  holds all three paths with stand-ins, because `$addonsToLoad` keeps the real add-on out.
  **Its lines carry no personal data and nothing above `debug` fires on a run that did nothing** —
  the cron runs every five minutes, so an `info` line per idle run is 288 a day. `SendDigestTest`
  records the logger and holds both.

`Listener::appAdminSetup` registers a separate `logdigest.test` factory, used only by the ACP test
tool.

`Listener::adminApiRedaction` answers the `hampel_admin_api_redaction` event with this add-on's
secret settings — an empty list, as it has none. **An option or `config.php` key that holds a
credential is added there in the same commit**; `SecretsDeclarationTest` fails on an undeclared
option whose name reads like one. The event belongs to another add-on and is not a dependency:
where nothing defines it, it never fires.

### Adding a log type is a subclass plus a list entry

`Digest/AbstractDigest` holds all the logic; `ServerErrorLog` and `AdminLog` only answer its
abstract methods — option ID, entity (`XF:ErrorLog`, `XF:AdminLog`), timestamp column, email
template, the fields compared for de-duplication, and the ACP route linked from the email. A new
type also needs an entry in `digest.list`, an array option shaped like the existing ones
(`enabled`, `frequency`, `limit`, `deduplicate`), an email template, and a `Test/` class if the
test tool should offer it.

### The last-checked time is the only state, and it is a simple-cache value

`Repository/DigestCache` stores one integer per log type in XF's simple cache, under the set
`Hampel/LogDigest`, keyed by entity ID. There is no table and nothing for `Setup` to install.
`getLastChecked()` still accepts the pre-3.1 array shape (`['checked' => …]`) — keep that branch.

### What a digest sends is the newest logs, not a backlog

Since 3.1.0 `fetchLogs()` takes up to 200 entries newer than last-checked, **newest first**, and
`prepareLogs()` trims to the option's `limit`, counting only non-duplicates — a duplicate is kept
in the output and flagged, so the email can say how many were collapsed. After sending,
last-checked is set to `\XF::$time`, not to the newest log's timestamp, so anything beyond the
limit is skipped rather than carried into the next email. `frequency` (minutes) is enforced inside
`getLogs()`, because the cron itself fires every five minutes regardless.

### ACP tools extend `XF\Admin\Controller\Tools`

`XF/Admin/Controller/Tools` adds two actions, both behind the `option` admin permission:

- **`actionTestLogDigest`** sends a test email from all logs (last-checked 0) without touching
  stored state; the server error test can first log a synthetic exception so there is something
  to send.
- **`actionResetLogDigest`** sets a type's last-checked back to 0, so the next cron run sends the
  most recent logs again.

The recipient comes from the `logdigestEmail` option and falls back to the board's contact
address (`Option/Email`); dates in the email use `logdigestTimeZone`.

### `log-digest:config` and `log-digest:validate` report on a forum from the command line

The add-on has both because it sends email unattended: a cron runs with nobody watching, and what
it is doing is spread across the options page, the reset tool and the cron list.

- **The checks live in `Service/HealthCheck`, and the commands only render them.** `run()` reads
  and never writes or sends; `sendTestDigest()` is the one method that sends. A check that throws
  becomes a failed row and a server error log entry, and the rest still report.
- **`Cli/RendersReport.php` is a whole copy of a trait shared between add-ons.** Do not edit it
  here: a fix goes into the copy it came from and is taken again. Its method names are those of
  `hampel/console-report`.
- **The exit code is a contract**: 0 when fine, 1 on any failure, and 2 for warnings only under
  `--strict`. `--unattended` skips the test digest and nothing else.
- **Every server error log row the add-on writes begins with `LogDigest: `**
  (`HealthCheck::ERROR_LOG_PREFIX`), so anything that mirrors that log can skip its rows.
- **A command class that cannot load stops `cmd.php` for the whole forum**, so commands extend
  Symfony's `Command`, never define a method called `run()`, and never write a grey colour tag
  directly. `CommandClassesTest` holds all three.
- **Add a check to `HealthCheck` whenever the add-on comes to depend on something new** in its
  environment — a new option that can be wrong, a file, a service.

## XenForo development data

`_output/` is the development source of truth for options, phrases, templates, the cron entry,
admin navigation, the class extension and the listeners; `_data/` is generated at export and
gitignored. After editing anything in `_output/`, run `xf-dev:import` above so the database
matches. Templates and phrases carry the `logdigest_` prefix.

Release packaging is in `build.json`: its `exec` steps delete the dev-only files from the build
and then move the remaining root `*.md` files to the zip root. **A dev-only markdown file has to be
removed before that `mv` runs**, or it ships at the top of the release zip.
