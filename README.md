# laravel-db-safe-mode

[![tests](https://github.com/cbz1991/laravel-db-safe-mode/actions/workflows/tests.yml/badge.svg)](https://github.com/cbz1991/laravel-db-safe-mode/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/cabuz/laravel-db-safe-mode)](https://packagist.org/packages/cabuz/laravel-db-safe-mode)
[![Total Downloads](https://img.shields.io/packagist/dt/cabuz/laravel-db-safe-mode)](https://packagist.org/packages/cabuz/laravel-db-safe-mode)
[![License](https://img.shields.io/packagist/l/cabuz/laravel-db-safe-mode)](LICENSE)

Work with **production data locally without fear**.

When you copy a production dump onto your machine, or connect your local app
straight to the production database to chase a bug you can't reproduce, there's
always that nagging worry: *what if a forgotten job, cron, listener or observer
fires and emails a real customer — or flips a flag on a real user?*

This package makes that impossible by default. In any non-production
environment it:

- **Blocks outbound side effects**, always: mail, notifications and
  unexpected HTTP calls are redirected or refused, so no real person hears
  from your machine.
- **Detects real data automatically**, even if you imported a dump and forgot.
- **Blocks database writes and stops queue workers and the scheduler** on real
  data, so a stray `$user->update(['is_premium' => true])` throws instead of
  persisting — unless you switch to debug mode to reproduce a bug.
- **Masks PII on read** (emails, phones, …) and never lets the fake value be
  written back.

It engages itself; you don't have to remember to turn it on. On everyday
local data (seeded with factories) it stays out of your way.

## Requirements

- PHP 8.1 - 8.5
- Laravel 10, 11, 12 or 13

## Installation

This is a **development-only** tool — install it as a dev dependency:

```bash
composer require --dev cabuz/laravel-db-safe-mode
php artisan vendor:publish --tag=safe-mode-config
```

Because it lives in `require-dev`, it is never installed on your production
servers (`composer install --no-dev`), so its service provider is never even
discovered there. As a second line of defence it also disables itself in the
`production` environment. It is **off in `production`** and **on everywhere
else**.

## Modes

| | `strict` | `debug` |
|---|---|---|
| Database writes | blocked | **allowed** |
| Queue workers you start (`queue:work`, `queue:listen`, `horizon`) | refused | **allowed** |
| Scheduler (`schedule:run`, `schedule:work`) | refused | refused |
| Mail | redirected or blocked | redirected or blocked |
| Notifications (SMS, Slack, …) | blocked | blocked |
| Outbound HTTP | blocked (allowlist) | blocked (allowlist) |

Pick one in `.env`:

```env
SAFE_MODE=debug                 # or strict
SAFE_MODE_MAIL_TO=you@company.com
```

When `SAFE_MODE` is not set, the data decides: **strict** if real data is
detected, **debug** otherwise. Every row of the table can be changed per mode
in `config/safe-mode.php`.

Real data is detected on boot by peeking at one column (`users.email` by
default). Values at an anonymized domain (`example.com`, `example.org` or
`example.net`, which covers Laravel's `UserFactory`) don't count, and neither
do your own developer accounts:

```env
SAFE_MODE_IGNORE_EMAILS=me@gmail.com,me@company.com   # SAFE_MODE_MAIL_TO is ignored too
```

Once at least 10 real addresses are found (`detect_real_data.min_real_emails`),
the data is treated as real and a red banner is printed before every artisan
command. A production clone has far more than that, while a local database
with a couple of your own accounts stays in debug mode.

> **Careful:** `debug` allows writes on whatever database you're connected to.
> Don't leave it on when you connect to the live production database — the
> banner (`DEBUG MODE on REAL DATA … writes ALLOWED`) is there to remind you.

## Scenarios

**1. You connect your local app to the live production DB.**
Leave `SAFE_MODE` unset (or set it to `strict`). Reads work normally; any
write throws a `WriteBlockedException`; queue workers and the scheduler refuse
to start; mail, notifications and stray HTTP calls are blocked.

**2. You clone production to debug a bug you can't reproduce.**
Say renewal emails aren't sent and a flag isn't set. Set `SAFE_MODE=debug` and
`SAFE_MODE_MAIL_TO=you@company.com`, then run the job by hand
(`php artisan queue:work --once`). It can write to your clone, so you can see
whether the flag gets set — but every email lands in your inbox, and SMS,
Slack or payment calls are blocked.

**3. You import a dump and forget it's real.**
With `SAFE_MODE` unset, real data means strict mode: a forgotten job, cron or
listener can't write, and nothing leaves the building.

## Running the write you *do* mean to (strict mode)

```php
use Cabuz\SafeMode\Facades\SafeMode;

SafeMode::allowWrites(function () {
    $user->is_premium = true;
    $user->save();
});
```

Migrations, seeding and framework tables (`migrations`, `jobs`, `cache`,
`sessions`, …) are always writable — see `writes.allow_tables` in the config.

## Masking PII

When real data is detected or `SAFE_MODE` is set, models using the trait show
masked values. Add it to a model and declare what to mask:

```php
use Cabuz\SafeMode\Masking\MasksAttributes;

class User extends Model
{
    use MasksAttributes;

    protected array $maskedAttributes = [
        'email' => 'email',   // real.person@gmail.com -> user_42@example.com
        'phone' => 'phone',
    ];
}
```

The real value is restored before any write, so masking can never corrupt the
source data. Register custom maskers with
`Maskers::extend('name', fn ($value, $model) => ...)`.

## Anonymizing a local dump for good

```bash
php artisan safe-mode:anonymize
```

Rewrites the configured PII columns in a **local** copy, permanently. Refuses
to run in a production environment.

## Status

```bash
php artisan safe-mode:status
```

Shows the mode, whether real data was detected, and what is currently blocked
or allowed.

## How it works

- **Writes:** guarded `Connection` subclasses registered via
  `Connection::resolverFor()` check every query in `run()` and throw on writes
  unless the target is an allowlisted infrastructure table or writes are
  explicitly permitted. Reads are untouched.
- **Mail:** a `MessageSending` listener redirects every message, on every
  mailer, to one inbox, or cancels it and logs the recipients.
- **Notifications:** a `NotificationSending` listener drops channels that reach
  real people.
- **HTTP:** a global request middleware throws on any host not allowlisted.
- **Queue & scheduler:** a `CommandStarting` listener refuses `queue:work`,
  `queue:listen`, `horizon`, `schedule:run` and `schedule:work` when the mode
  halts them.

## Octane & long-lived workers

On Octane the worker process is reused across many requests, so transient state
must not leak between them. The package resets its write-permission state at the
start of every Octane request, task and tick (listening by event name, so
`laravel/octane` is not a dependency). The environment-derived state — whether
the guard is engaged, its mode, and whether real data is present — is
preserved, since it doesn't change within a worker's lifetime.

The guarded connection resolvers are registered on the `Connection` class
itself, so they survive Octane recycling connections between requests.

`allowWrites()` is request-scoped and uses `try/finally`, so it always releases
its permission even when the wrapped code throws.

## Configuration

Every guard is independently toggleable in `config/safe-mode.php`. See the
inline comments there.

## Contributing

Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) for
the development setup, the support matrix and the pull request guidelines.

Found a way around one of the guards? Please report it privately — see
[SECURITY.md](SECURITY.md).

## License

MIT
