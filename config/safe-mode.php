<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch. When null (the default) the package decides for itself:
    | it stays OFF in the "production" environment and ON everywhere else.
    | This is deliberate — the whole point is that you never have to remember
    | to turn it on. Set it to an explicit true/false only if you know better.
    |
    */

    'enabled' => env('SAFE_MODE_ENABLED', null),

    /*
    |--------------------------------------------------------------------------
    | Environments considered "real production"
    |--------------------------------------------------------------------------
    |
    | When app()->environment() matches one of these, the package disables
    | itself and lets the app run normally. Everywhere else it engages.
    |
    */

    'production_environments' => ['production', 'prod'],

    /*
    |--------------------------------------------------------------------------
    | Real-data detection (defense in depth)
    |--------------------------------------------------------------------------
    |
    | Even in local/staging you may have imported a production dump and
    | forgotten. On boot the package peeks at one column and, if the values
    | do NOT look anonymized, it treats the data as real and clamps down hard
    | (writes blocked, outbound blocked, loud banner) regardless of settings.
    |
    */

    'detect_real_data' => [
        'enabled' => true,
        'connection' => null, // null = default connection
        'table' => 'users',
        'column' => 'email',
        // A row counts as "real" when the value does NOT end with this.
        'anonymized_suffix' => '@example.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database write protection
    |--------------------------------------------------------------------------
    |
    | Make the guarded connections read-only at the PDO layer. Any
    | insert/update/delete/raw-statement throws a WriteBlockedException.
    | This is what stops that forgotten job from flipping a flag on a real
    | user. Wrap intentional writes in SafeMode::allowWrites(fn () => ...).
    |
    */

    'writes' => [
        'block' => true,
        // null = guard every connection; or list connection names.
        'connections' => null,

        // Framework/infrastructure tables whose writes are always permitted.
        // These never hold real-user PII, and blocking them would break
        // migrations, the queue, cache and sessions. Your domain tables
        // (users, orders, …) are deliberately NOT here.
        'allow_tables' => [
            'migrations',
            'jobs', 'job_batches', 'failed_jobs',
            'cache', 'cache_locks',
            'sessions',
            'password_reset_tokens',
            'telescope_entries', 'telescope_entries_tags', 'telescope_monitoring',
        ],

        // Console commands during which writes are also permitted. These are
        // explicit developer actions (schema changes, seeding). Glob patterns
        // ("*") are supported. app:* commands, tinker and queued jobs stay
        // blocked unless wrapped in SafeMode::allowWrites().
        'allow_during_commands' => [
            'migrate', 'migrate:*',
            'db:seed', 'db:wipe',
            'schema:*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbound side effects
    |--------------------------------------------------------------------------
    |
    | Nothing leaves the building. Each guard can be toggled independently.
    |
    */

    'outbound' => [
        // Redirect all mail, on every mailer, to a single dev inbox. Cc and Bcc
        // are dropped; the original recipients are kept in the
        // X-Safe-Mode-Original-Recipients header. null = don't send at all,
        // just log the recipients and subject.
        'mail' => [
            'block' => true,
            'redirect_to' => env('SAFE_MODE_MAIL_TO', null),
        ],

        // Swallow notifications on channels that reach real people.
        'notifications' => [
            'block' => true,
            'allowed_channels' => ['mail', 'database'], // these still run (mail is caught above)
        ],

        // Throw on any unexpected outgoing HTTP call made via Http::.
        'http' => [
            'block' => true,
            'allowed_hosts' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue & scheduler
    |--------------------------------------------------------------------------
    |
    | "halt" prevents queued jobs and scheduled commands from running at all
    | while real data is present, so nothing fires behind your back. Run the
    | one job you care about by hand instead.
    |
    */

    'queue' => [
        'halt' => true,
    ],

    'scheduler' => [
        'halt' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | On-the-fly masking (read side)
    |--------------------------------------------------------------------------
    |
    | Applied to Eloquent models using the MasksAttributes trait. Values are
    | masked when read and the original is restored before save, so a stray
    | save() can never persist a fake address back to production.
    |
    */

    'masking' => [
        'enabled' => true,
        // attribute => masker (a callable key from the list below, or a closure in a service provider)
        'attributes' => [
            // 'email' => 'email',
            // 'phone' => 'phone',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Banner
    |--------------------------------------------------------------------------
    */

    'banner' => [
        'console' => true, // print a warning before every artisan command
    ],

];
