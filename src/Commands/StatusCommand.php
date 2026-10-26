<?php

namespace Cabuz\SafeMode\Commands;

use Cabuz\SafeMode\Support\SafeMode;
use Illuminate\Console\Command;

class StatusCommand extends Command
{
    protected $signature = 'safe-mode:status';

    protected $description = 'Show whether safe-mode is engaged and what it is guarding';

    public function handle(SafeMode $guard): int
    {
        if (! $guard->isActive()) {
            $this->components->info('safe-mode is NOT engaged (production, or disabled by config).');

            return self::SUCCESS;
        }

        $this->components->warn('safe-mode is ENGAGED — '.$guard->reason());
        $this->newLine();

        $this->components->twoColumnDetail('Real data detected', $guard->isRealData() ? '<fg=red>yes</>' : 'no');
        $this->components->twoColumnDetail('Database writes', config('safe-mode.writes.block') ? '<fg=green>blocked</>' : 'allowed');
        $this->components->twoColumnDetail('Outbound mail', config('safe-mode.outbound.mail.block') ? '<fg=green>blocked</>' : 'allowed');
        $this->components->twoColumnDetail('Notifications', config('safe-mode.outbound.notifications.block') ? '<fg=green>blocked</>' : 'allowed');
        $this->components->twoColumnDetail('Outbound HTTP', config('safe-mode.outbound.http.block') ? '<fg=green>blocked</>' : 'allowed');
        $this->components->twoColumnDetail('Queue worker', config('safe-mode.queue.halt') ? '<fg=green>halted</>' : 'running');
        $this->components->twoColumnDetail('Scheduler', config('safe-mode.scheduler.halt') ? '<fg=green>halted</>' : 'running');

        return self::SUCCESS;
    }
}
