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
    | Mode
    |--------------------------------------------------------------------------
    |
    | "strict": block everything — database writes, queue workers, the
    |           scheduler, and outbound mail / notifications / HTTP.
    | "debug":  let writes and the queue workers you start by hand run, so you
    |           can reproduce a bug on a production clone; outbound mail,
    |           notifications and HTTP stay blocked, and the scheduler stays off.
    |
    | null (the default) decides from the data: "strict" when real data is
    | detected, "debug" otherwise, so everyday local development isn't
    | blocked. An unknown value falls back to "strict".
    |
    | Careful: "debug" allows writes on whatever database you're connected to.
    | Don't leave it on when you connect to the live production database.
    |
    */

    'mode' => env('SAFE_MODE'),

    'modes' => [
        'strict' => [
            'block_writes' => true,
            'halt_queue' => true,      // refuse queue:work, queue:listen, horizon
            'halt_scheduler' => true,  // refuse schedule:run, schedule:work
        ],
        'debug' => [
            'block_writes' => false,
            'halt_queue' => false,
            'halt_scheduler' => true,  // nothing should run behind your back
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Real-data detection
    |--------------------------------------------------------------------------
    |
    | On boot the package peeks at one column. If any value is not at an
    | anonymized domain, the data is treated as real: "strict" mode is used
    | unless SAFE_MODE says otherwise, PII is masked and a red banner is shown.
    |
    */

    'detect_real_data' => [
        'enabled' => true,
        'connection' => null, // null = default connection
        'table' => 'users',
        'column' => 'email',
        // A row counts as "real" when the value is not at one of these domains.
        // The defaults cover Faker's safeEmail(), which Laravel's UserFactory uses.
        'anonymized_domains' => ['example.com', 'example.org', 'example.net'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database write protection
    |--------------------------------------------------------------------------
    |
    | When the mode blocks writes, any insert/update/delete throws a
    | WriteBlockedException. This is what stops that forgotten job from
    | flipping a flag on a real user. Wrap intentional writes in
    | SafeMode::allowWrites(fn () => ...).
    |
    */

    'writes' => [
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
    | Nothing leaves the building, in every mode. Each guard can be toggled
    | independently.
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
    | On-the-fly masking (read side)
    |--------------------------------------------------------------------------
    |
    | Applied to Eloquent models using the MasksAttributes trait, when real
    | data is detected or SAFE_MODE is set. Values are masked when read and
    | the original is restored before save, so a stray save() can never
    | persist a fake address back to production.
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
        // Print the mode before every artisan command when real data is
        // detected or SAFE_MODE is set.
        'console' => true,
    ],

];
