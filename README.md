# laravel-db-safe-mode

Work with **production data locally without fear**.

When you copy a production dump onto your machine, or connect your local app
straight to the production database to chase a bug you can't reproduce, there's
always that nagging worry: *what if a forgotten job, cron, listener or observer
fires and emails a real customer — or flips a flag on a real user?*

This package makes that impossible by default. In any non-production
environment it:

- **Blocks database writes** at the connection layer — a stray
  `$user->update(['is_premium' => true])` throws instead of persisting.
- **Blocks outbound side effects** — mail, notifications and unexpected HTTP
  calls are redirected or refused.
- **Halts the queue worker and scheduler** while real data is present, so
  nothing runs behind your back.
- **Masks PII on read** (emails, phones, …) and never lets the fake value be
  written back.
- **Detects real data automatically** — even if you imported a dump and forgot.

It engages itself; you don't have to remember to turn it on.

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

## The three scenarios it's built for

**1. You connect your local app to the production DB.**
Reads work normally. Any write throws a `WriteBlockedException`. Mail,
notifications and stray HTTP calls are blocked. The queue worker refuses to
process jobs.

**2. You import a production dump locally and forget it's real.**
On boot the package peeks at one column (`users.email` by default). If the
values aren't anonymized it treats the data as real, clamps down hard, and
prints a red `REAL DATA` banner before every artisan command.

**3. A job/cron flips a flag on a user without sending any email.**
This is the subtle one. There's no outbound signal to catch — it's a silent
DB write. The connection-level write guard stops it dead.

## Running the write you *do* mean to

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

Add the trait to a model and declare what to mask:

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

Shows whether the guard is engaged, whether real data was detected, and what is
currently blocked.

## How it works

- **Writes:** guarded `Connection` subclasses registered via
  `Connection::resolverFor()` override every write entry point and throw unless
  the target is an allowlisted infrastructure table or writes are explicitly
  permitted. Reads are untouched.
- **Mail:** `Mailer::alwaysTo()` redirects to one inbox, or the `log` transport
  is forced.
- **Notifications:** a `NotificationSending` listener drops channels that reach
  real people.
- **HTTP:** a global request middleware throws on any host not allowlisted.
- **Queue:** a `Looping` listener pauses the daemon worker.
- **Scheduler:** a `ScheduledTaskStarting` listener aborts `schedule:run`.

## Octane & long-lived workers

On Octane the worker process is reused across many requests, so transient state
must not leak between them. The package resets its write-permission state at the
start of every Octane request, task and tick (listening by event name, so
`laravel/octane` is not a dependency). The environment-derived state — whether
the guard is engaged and whether real data is present — is preserved, since it
doesn't change within a worker's lifetime.

The guarded connection resolvers are registered on the `Connection` class
itself, so they survive Octane recycling connections between requests.

`allowWrites()` is request-scoped and uses `try/finally`, so it always releases
its permission even when the wrapped code throws.

## Configuration

Every guard is independently toggleable in `config/safe-mode.php`. See the
inline comments there.

## License

MIT
