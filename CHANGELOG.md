# Changelog

All notable changes to `laravel-db-safe-mode` will be documented in this file.

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
