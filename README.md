# Log Digest for XenForo

Emails a digest of XenForo's logs to an administrator, so errors are noticed without anyone having
to check the control panel.

## Logs

- Server error log
- Admin log

Each log type is enabled separately, with its own check frequency, maximum number of entries per
email, and optional collapsing of identical entries. The most recent entries are sent.

## Requirements

- XenForo 2.2.0 or later
- PHP 7.4 or later

## Usage

Set the recipient and each log type's options under Options > Log Digest. The recipient defaults to
the board's contact email address. Two tools under Tools send a test digest and reset a log type,
so its next digest starts again from the most recent entries.

## Commands

Two commands report on the add-on from the command line, for a forum you cannot log in to or a
monitor that needs an answer.

```bash
php cmd.php log-digest:config      # what is configured, and when each log type was last sent
php cmd.php log-digest:validate    # whether digests can be sent
```

`log-digest:config` prints the recipient, the time zone, each log type's settings and last digest
time, and the cron entry. It changes nothing and sends nothing.

`log-digest:validate` checks the recipient, the time zone and the cron entry, warns when no log type
is enabled, and fails when a log type has entries that have been waiting well past its frequency,
which means digests are not going out. It then sends a test digest to the recipient.

| option | effect |
|---|---|
| `--unattended` | skip the test digest, so nothing is sent |
| `--strict` | exit 2 when there are warnings and no failures |

### Exit codes

| | all fine | warnings only | any failure |
|---|---|---|---|
| `log-digest:validate` | 0 | 0 | 1 |
| `log-digest:validate --strict` | 0 | 2 | 1 |

**1 always means failed.** A warning exits 0 unless `--strict` is given, so the command can gate a
deploy. This is not the monitoring-plugin numbering, where 1 is a warning and 2 is critical.

## Links

By [Simon Hampel](https://xenforo.com/community/members/sim.4264/).

- [Add-on: Log Digest](https://xenforo.com/community/resources/log-digest.6130/)
- [Discussion and support](https://xenforo.com/community/threads/log-digest.142230/)
