# Changelog

All notable changes to `laravel-db-safe-mode` will be documented in this file.

## Unreleased

## 1.3.0 - 2026-10-26

- New: `strict` and `debug` modes, chosen with `SAFE_MODE` in `.env`.
  `strict` blocks writes, queue workers, the scheduler and outbound side
  effects. `debug` lets writes and the queue workers you start by hand run, so
  a bug can be reproduced on a production clone, while mail, notifications and
  HTTP stay blocked and the scheduler stays off. Each mode's settings are
  configurable under `modes`.
- Changed: without `SAFE_MODE`, the mode follows the data: `strict` when real
  data is detected, `debug` otherwise. Everyday local development on seeded
  data is no longer blocked (previously every write and the queue worker were
  blocked in any non-production environment).
- Changed: queue workers and the scheduler are now refused when their command
  starts (`queue:work`, `queue:listen`, `horizon`, `schedule:run`,
  `schedule:work`), which also covers `queue:work --once`. Previously only the
  `queue:work` loop was paused. New `QueueHaltedException`.
- Changed: PII masking only applies when real data is detected or `SAFE_MODE`
  is set.
- `safe-mode:status` and the console banner show the mode and what is blocked
  or allowed; the banner only appears on real data or when `SAFE_MODE` is set.
- The `writes.block`, `queue.halt` and `scheduler.halt` config keys are
  replaced by `modes`; in configs published earlier, setting them to `false`
  still turns that guard off.

## 1.2.3 - 2026-10-26

- Fix: the package crashed with a PHP fatal error on MySQL and MariaDB with
  Laravel 11+ (`GuardsWrites::insert()` was incompatible with
  `MySqlConnection::insert()`, which gained a `$sequence` parameter). Writes
  are now guarded only in `Connection::run()`, so no driver-specific method is
  overridden any more.
- Fix: writes behind a leading SQL comment (`/* job */ update ...`) and writes
  chained after another statement in one call (`select 1; delete from users`)
  are now blocked.
- Fix: real-data detection no longer aborts the surrounding transaction on
  PostgreSQL when the configured table doesn't exist.
- Real-data detection now treats `example.com`, `example.org` and
  `example.net` as anonymized (Faker's `safeEmail()`, used by Laravel's
  `UserFactory`), so seeded databases are no longer flagged as real. New
  `detect_real_data.anonymized_domains` option; the old `anonymized_suffix`
  setting is still honoured.
- Show `composer require --dev` on Packagist.
- CI: run the test suite against MySQL, MariaDB and PostgreSQL, and add
  PHPStan (Larastan, level 6) and Pint checks.
- Tests for the queue and scheduler halt, the `safe-mode:anonymize` and
  `safe-mode:status` commands, and real-data detection.

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
