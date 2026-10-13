# CHANGELOG

## 3.3.0 (2026-10-13)

* requires PHP 7.4 or later
* new command `log-digest:config` prints the recipient, each log type's settings and last digest time, and the cron entry
* new command `log-digest:validate` checks that digests can be sent and sends a test digest; `--unattended` skips the send, and `--strict` exits 2 on warnings
* server error log entries written by the add-on now begin `LogDigest: `
* works with both the 4.x and 5.x versions of the Monolog Logging Service add-on
* a disabled log type is logged at `debug` rather than `info`, and log lines no longer include the recipient's email address

## 3.2.0 (2026-09-30)

* requires XenForo 2.2.0 or later; the separate PHP 7.0 requirement is dropped, as XenForo 2.2 enforces it
* security: request state in the server error digest is now escaped - on forums running jobs from the command line, HTML in a request that caused an error could reach the digest email
* a digest that fails to send is retried on the next run, rather than its log entries being skipped
* the digest email header no longer adds a stray "s" after the log name
* the reset tool ignores an unknown log type rather than raising an undefined index warning
* the limit option explains that 0 still stops at 200 entries
* removed three unused phrases

## 3.1.3 (2025-12-12)

* run enqueuePostUpgradeCleanUp during upgrades if we're running XF2.3+

## 3.1.2 (2024-10-16)

* assert admin permission on tools

## 3.1.1 (2024-08-23)

* minor bugfixes

## 3.1.0 (2021-05-28)

* major logic change - addon now sends most recent logs rather than oldest logs
* added support for Admin Log digest

## 3.0.1 (2021-05-20)

* bug fix - undeclared variable
* added monolog logging support for debug purposes

## 3.0.0 (2021-05-20)

* complete rebuild to support multiple log types and to make log collection more robust

## 2.1.2 (2020-05-16)

* add version requirements to addon.json
* bugfix: variable may not have been defined when we used it
* use release version of hampel/xenforo-test-framework

## 2.1.1 (2019-10-21)

* fixed a bug in method name for cron task

## 2.1.0 (2019-10-21)

* major restructure and rewrite of code to use repositories and services

## 2.0.0 (2018-08-12)

* changed addon_id from LogDigest to Hampel/LogDigest

## 1.0.1 (2018-02-04)

* bug fix: wasn't allowing unlimited log entries (limit = 0)
* display last reset time using timezone configured in options

## 1.0.0 (2018-02-03)

* initial working version
