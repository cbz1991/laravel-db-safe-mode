<?php

namespace Cabuz\SafeMode;

use Cabuz\SafeMode\Commands\AnonymizeCommand;
use Cabuz\SafeMode\Commands\StatusCommand;
use Cabuz\SafeMode\Guards\CommandGuard;
use Cabuz\SafeMode\Guards\OutboundGuard;
use Cabuz\SafeMode\Guards\WriteGuard;
use Cabuz\SafeMode\Masking\Maskers;
use Cabuz\SafeMode\Support\Detector;
use Cabuz\SafeMode\Support\SafeMode;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class SafeModeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/safe-mode.php', 'safe-mode');

        $this->app->singleton(SafeMode::class);
        $this->app->singleton(Maskers::class);

        // The write guard must be in place before any connection is resolved,
        // so register the resolvers as early as possible. Actual blocking is
        // gated at query time by SafeMode::blocksWrites(), which depends on the
        // mode boot() picks — so installing the guarded connections is safe.
        // (Older published configs can still switch it off with writes.block.)
        if (config('safe-mode.writes.block', true) !== false) {
            WriteGuard::register();
        }
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/safe-mode.php' => config_path('safe-mode.php'),
        ], 'safe-mode-config');

        if ($this->app->runningInConsole()) {
            $this->commands([AnonymizeCommand::class, StatusCommand::class]);
        }

        $detector = $this->detector();

        if (! $detector->shouldEngage()) {
            return;
        }

        /** @var SafeMode $guard */
        $guard = $this->app->make(SafeMode::class);

        $realData = $detector->hasRealData();
        $mode = $detector->mode($realData);
        $explicit = $detector->modeIsExplicit();

        $guard->engage(
            match (true) {
                $explicit => "SAFE_MODE is set to {$mode}",
                $realData => 'real data detected',
                default => 'no real data detected',
            },
            $realData,
            $mode,
            $detector->modeSettings($mode),
            $explicit,
        );

        $this->registerGuards($guard);
        $this->registerConsoleBanner();
        $this->registerWriteAllowlist($guard);
        $this->registerStateResets($guard);
    }

    /**
     * On long-lived runtimes (Octane) the worker process is reused across many
     * requests, tasks and ticks. Wipe the transient write-permission state at
     * the start of each unit of work so it can never leak between them.
     *
     * Listeners are registered by event name, so laravel/octane does not need
     * to be installed; if it never fires these events, nothing happens.
     */
    protected function registerStateResets(SafeMode $guard): void
    {
        $events = $this->app['events'];

        foreach ([
            'Laravel\Octane\Events\RequestReceived',
            'Laravel\Octane\Events\TaskReceived',
            'Laravel\Octane\Events\TickReceived',
        ] as $event) {
            $events->listen($event, fn () => $guard->reset());
        }
    }

    /**
     * Permit writes for the duration of explicit, intentional commands
     * (migrations, seeding, framework housekeeping).
     */
    protected function registerWriteAllowlist(SafeMode $guard): void
    {
        if (! $this->app->runningInConsole() || ! $guard->blocksWrites()) {
            return;
        }

        $patterns = (array) config('safe-mode.writes.allow_during_commands', []);
        $events = $this->app['events'];

        $matches = fn (?string $command): bool => $command !== null
            && collect($patterns)->contains(fn ($p) => Str::is($p, $command));

        $events->listen(CommandStarting::class, function (CommandStarting $event) use ($guard, $matches) {
            if ($matches($event->command)) {
                $guard->pushAllowWrites();
            }
        });

        $events->listen(CommandFinished::class, function (CommandFinished $event) use ($guard, $matches) {
            if ($matches($event->command)) {
                $guard->popAllowWrites();
            }
        });
    }

    protected function registerGuards(SafeMode $guard): void
    {
        (new OutboundGuard($this->app, config('safe-mode.outbound', [])))->register();

        CommandGuard::register($this->app['events'], $guard);
    }

    protected function registerConsoleBanner(): void
    {
        if (! $this->app->runningInConsole() || ! config('safe-mode.banner.console', true)) {
            return;
        }

        $this->app['events']->listen(CommandStarting::class, function (CommandStarting $event) {
            /** @var SafeMode $guard */
            $guard = $this->app->make(SafeMode::class);

            // Stay quiet on everyday local data, and don't clutter our own output.
            if ((! $guard->isRealData() && ! $guard->isExplicitMode())
                || str_starts_with((string) $event->command, 'safe-mode:')) {
                return;
            }

            $event->output->writeln(static::bannerFor($guard));
        });
    }

    public static function bannerFor(SafeMode $guard): string
    {
        $label = strtoupper($guard->mode()).' MODE'.($guard->isRealData() ? ' on REAL DATA' : '');

        $state = fn (bool $blocked, string $what) => $blocked ? "{$what} blocked" : "<options=bold>{$what} ALLOWED</>";

        return "<bg=red;fg=white;options=bold> {$label} </> <fg=yellow>"
            .$state($guard->blocksWrites(), 'writes').', '
            .$state($guard->haltsQueue(), 'queue workers').', '
            .$state($guard->haltsScheduler(), 'scheduler')
            .'; mail, notifications & HTTP guarded</>';
    }

    protected function detector(): Detector
    {
        return new Detector($this->app, config('safe-mode') ?? require __DIR__.'/../config/safe-mode.php');
    }
}
