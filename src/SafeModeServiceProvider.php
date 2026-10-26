<?php

namespace Cabuz\SafeMode;

use Cabuz\SafeMode\Commands\AnonymizeCommand;
use Cabuz\SafeMode\Commands\StatusCommand;
use Cabuz\SafeMode\Guards\OutboundGuard;
use Cabuz\SafeMode\Guards\WriteGuard;
use Cabuz\SafeMode\Masking\Maskers;
use Cabuz\SafeMode\Support\Detector;
use Cabuz\SafeMode\Support\SafeMode;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Foundation\Application;
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
        // gated at query time by SafeMode::isActive(), which boot() sets — so
        // installing the guarded connections here is always safe.
        if (config('safe-mode.writes.block', true)) {
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

        $guard->engage(
            $realData
                ? 'real data detected in a non-production environment'
                : 'non-production environment',
            $realData,
        );

        $this->registerGuards($guard);
        $this->registerConsoleBanner();
        $this->registerWriteAllowlist($guard);
    }

    /**
     * Permit writes for the duration of explicit, intentional commands
     * (migrations, seeding, framework housekeeping).
     */
    protected function registerWriteAllowlist(SafeMode $guard): void
    {
        if (! $this->app->runningInConsole() || ! config('safe-mode.writes.block', true)) {
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
        $outbound = new OutboundGuard($this->app, config('safe-mode.outbound', []));

        $outbound->register();

        if (config('safe-mode.queue.halt', true)) {
            $outbound->registerQueueHalt();
        }

        if (config('safe-mode.scheduler.halt', true)) {
            $outbound->registerSchedulerHalt();
        }
    }

    protected function registerConsoleBanner(): void
    {
        if (! $this->app->runningInConsole() || ! config('safe-mode.banner.console', true)) {
            return;
        }

        $this->app['events']->listen(CommandStarting::class, function (CommandStarting $event) {
            /** @var SafeMode $guard */
            $guard = $this->app->make(SafeMode::class);

            // Don't clutter our own status output.
            if (str_starts_with((string) $event->command, 'safe-mode:')) {
                return;
            }

            $label = $guard->isRealData() ? 'REAL DATA' : 'safe-mode';
            $event->output->writeln(
                "<bg=red;fg=white;options=bold> {$label} </> <fg=yellow>guard engaged — writes & outbound side effects are blocked</>"
            );
        });
    }

    protected function detector(): Detector
    {
        return new Detector($this->app, config('safe-mode') ?? require __DIR__.'/../config/safe-mode.php');
    }
}
