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

## Usage

Set the recipient and each log type's options under Options > Log Digest. The recipient defaults to
the board's contact email address. Two tools under Tools send a test digest and reset a log type,
so its next digest starts again from the most recent entries.

## Links

By [Simon Hampel](https://xenforo.com/community/members/sim.4264/).

- [Add-on: Log Digest](https://xenforo.com/community/resources/log-digest.6130/)
- [Discussion and support](https://xenforo.com/community/threads/log-digest.142230/)
