# Contributing

Thanks for considering a contribution to `laravel-db-safe-mode`! Bug reports,
fixes, new guards and documentation improvements are all welcome.

Please read this guide before opening an issue or a pull request. By taking
part in this project you agree to follow the [Code of Conduct](CODE_OF_CONDUCT.md).

## Before you start

- **Bugs:** search the [existing issues](https://github.com/cbz1991/laravel-db-safe-mode/issues)
  first. If nobody has reported it yet, open a bug report using the template.
- **New features:** open a feature request and discuss it *before* writing code.
  This package is deliberately small and focused; an agreed design saves you
  from a pull request that can't be merged.
- **Security issues:** do **not** open a public issue. Follow the
  [security policy](SECURITY.md) instead.

## The one rule that matters

This package exists to stop accidents on real data. Every change must keep it
**fail-safe**:

- When in doubt, block. A guard that blocks too much is an annoyance; a guard
  that lets a real email or a real write through is a bug.
- The package must stay **off in production** and **on everywhere else** by
  default. Never change that default.
- A guard must never crash the host application on boot. If it depends on an
  optional library (e.g. Guzzle), check that it is installed and skip quietly
  when it isn't.

## Development setup

Requirements: PHP 8.1+ and Composer.

```bash
git clone https://github.com/<your-username>/laravel-db-safe-mode.git
cd laravel-db-safe-mode
composer install
composer test
```

Tests run on [Orchestra Testbench](https://github.com/orchestral/testbench) with
an in-memory SQLite database, so you don't need a database server.

## Supported versions

Every change must work on the full support matrix:

| Laravel | PHP |
|---|---|
| 10 | 8.1, 8.2, 8.3 |
| 11 | 8.2, 8.3, 8.4 |
| 12 | 8.2, 8.3, 8.4, 8.5 |
| 13 | 8.3, 8.4, 8.5 |

CI runs every combination on each pull request. To check a specific Laravel
version locally:

```bash
composer require --dev "laravel/framework:10.*" --no-update
composer update --with-all-dependencies
composer test
```

(Revert `composer.json` afterwards; don't commit the pinned version.)

Concretely, this means:

- No syntax newer than PHP 8.1 (no `readonly` classes, no DNF types, no typed
  class constants).
- No Laravel API that doesn't exist in Laravel 10. If you need one, guard it
  with `method_exists()` / `class_exists()` and degrade gracefully.

## Coding guidelines

- Follow PSR-12 and the existing Laravel-style formatting of the codebase.
- Match the surrounding code: naming, comment density and structure.
- Keep each guard self-contained and independently toggleable through
  `config/safe-mode.php`. Any new option needs a sensible, safe default and an
  inline comment in the config file explaining it.
- Don't add new runtime dependencies to `require`. Optional integrations go in
  `suggest` and must be detected at runtime.
- Public API changes (facade methods, config keys, command names, exception
  classes) need a clear reason, since they affect every user.

## Tests

- Every bug fix needs a test that fails without the fix.
- Every new guard or option needs tests for both the blocked and the allowed
  path.
- Tests live in `tests/`; the `User` fixture model and the migrations in
  `tests/database/migrations` cover most scenarios.

## Pull requests

1. Fork the repository and create a branch from `main`
   (e.g. `fix/notification-channel-check`, `feature/sms-guard`).
2. Make your change, with tests.
3. Run `composer test` and make sure everything passes.
4. Add a line under an `## Unreleased` heading at the top of
   [`CHANGELOG.md`](CHANGELOG.md) describing the change from a user's point of
   view.
5. Update `README.md` if the behaviour or configuration changed.
6. Open the pull request against `main` and fill in the template.

Guidelines for a pull request that is easy to review and merge:

- **One change per pull request.** Split unrelated fixes into separate ones.
- **Small and focused.** Don't mix refactoring or reformatting with a fix.
- **Clear commit messages** in the imperative mood, with a short summary line,
  e.g. `Skip the HTTP guard when Guzzle is not installed`.
- **Green CI.** Pull requests with failing checks won't be reviewed until they
  pass.

## Releases

Releases are handled by the maintainer. Don't change version numbers or create
tags in your pull request; the maintainer decides the version (following
[Semantic Versioning](https://semver.org/)) and moves the `Unreleased`
changelog entries under it.

## License

By contributing, you agree that your contributions will be licensed under the
project's [MIT License](LICENSE).
