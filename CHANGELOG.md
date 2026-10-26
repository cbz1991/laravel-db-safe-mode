# Changelog

All notable changes to `laravel-db-safe-mode` will be documented in this file.

## Unreleased

## 1.2.2 - 2026-10-26

- Fix: on PostgreSQL, `Model::create()` / `insertGetId()` were not blocked,
  because Laravel runs them as `insert ... returning id` through
  `selectFromWriteConnection()`. Every query is now checked, whichever
  connection method sends it, including data-modifying CTEs
  (`with ... update ...`).
- Fix: setting `SAFE_MODE_MAIL_TO` crashed the app on boot
  (`Mailer::alwaysCc()` doesn't exist). Mail is now guarded through the
  `MessageSending` event, which also covers non-default mailers
  (`Mail::mailer('ses')`). Redirected mail drops Cc/Bcc and keeps the original
  recipients in an `X-Safe-Mode-Original-Recipients` header; without a
  redirect address the email is not sent and its recipients and subject are
  logged.
- Fix: the notification guard returned `true` for allowed channels, which
  stopped the application's own `NotificationSending` listeners from running.
- Add contributing guide, security policy, code of conduct, and issue / pull
  request templates.
- Suggest `guzzlehttp/guzzle` in `composer.json` (needed for the HTTP guard on
  Laravel 10, bundled with Laravel 11+).

## 1.2.1 - 2026-10-26

- Support PHP 8.1 - 8.5 and Laravel 10 - 13.
- Fix a crash on boot for apps without Guzzle (Laravel 10 does not require it):
  the HTTP guard is now only registered when Guzzle is installed.
- Fix CI: allow end-of-life Laravel releases that have security advisories to be
  installed (Composer 2.9+ blocks them by default), and test every officially
  supported PHP / Laravel combination.

## 1.2.0 - 2026-10-26

- Add Laravel 13 support (`illuminate/*: ^13.0`, testbench `^11.0`).
- Fix CI: resolve the testbench version from the package constraints instead of
  injecting it from the matrix (the injection broke dependency installation on
  the Laravel 11 jobs), update dependencies with `--with-all-dependencies`, and
  authenticate Composer to avoid GitHub API rate limits.

## 1.1.0 - 2026-10-26

- Octane & long-lived worker support: reset the transient write-permission
  state at the start of every Octane request, task and tick so it cannot leak
  between units of work. Registered by event name, so `laravel/octane` is not
  required.
- Add `SafeMode::reset()`.

## 1.0.1 - 2026-10-26

- Drop `minimum-stability: dev` now that a stable release is tagged; the
  package resolves against stable dependencies by default.

## 1.0.0 - 2026-10-26

Initial release.

- Connection-level write guard with an infrastructure-table allowlist and an
  `allowWrites()` escape hatch.
- Outbound guards for mail, notifications and HTTP.
- Queue worker and scheduler halting while real data is present.
- On-read PII masking via the `MasksAttributes` trait, with the original value
  restored before writes.
- Automatic real-data detection and a console banner.
- `safe-mode:anonymize` and `safe-mode:status` commands.
