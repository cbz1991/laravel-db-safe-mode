<?php

namespace Cabuz\SafeMode\Commands;

use Cabuz\SafeMode\Support\SafeMode;
use Illuminate\Console\Command;

class StatusCommand extends Command
{
    protected $signature = 'safe-mode:status';

    protected $description = 'Show whether safe-mode is engaged, in which mode, and what it is guarding';

    public function handle(SafeMode $guard): int
    {
        if (! $guard->isActive()) {
            $this->components->info('safe-mode is NOT engaged (production, or disabled by config).');

            return self::SUCCESS;
        }

        $this->components->warn("safe-mode is ENGAGED in {$guard->mode()} mode — {$guard->reason()}.");
        $this->newLine();

        $blocked = fn (bool $blocked, string $yes = 'blocked', string $no = 'allowed') => $blocked
            ? "<fg=green>{$yes}</>"
            : "<fg=red>{$no}</>";

        $this->components->twoColumnDetail('Mode', $guard->mode().($guard->isExplicitMode() ? ' (SAFE_MODE)' : ' (auto)'));
        $this->components->twoColumnDetail('Real data detected', $guard->isRealData() ? '<fg=red>yes</>' : 'no');
        $this->components->twoColumnDetail('Database writes', $blocked($guard->blocksWrites()));
        $this->components->twoColumnDetail('Queue workers', $blocked($guard->haltsQueue(), 'halted', 'allowed'));
        $this->components->twoColumnDetail('Scheduler', $blocked($guard->haltsScheduler(), 'halted', 'allowed'));
        $this->components->twoColumnDetail('Outbound mail', $this->mailState());
        $this->components->twoColumnDetail('Notifications', $blocked((bool) config('safe-mode.outbound.notifications.block', true)));
        $this->components->twoColumnDetail('Outbound HTTP', $blocked((bool) config('safe-mode.outbound.http.block', true)));
        $this->components->twoColumnDetail('PII masking', $guard->masksData() && config('safe-mode.masking.enabled', true) ? 'on' : 'off');

        return self::SUCCESS;
    }

    protected function mailState(): string
    {
        if (! config('safe-mode.outbound.mail.block', true)) {
            return '<fg=red>allowed</>';
        }

        $redirectTo = config('safe-mode.outbound.mail.redirect_to');

        return $redirectTo ? "<fg=green>redirected to {$redirectTo}</>" : '<fg=green>blocked</>';
    }
}
