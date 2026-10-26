<?php

namespace Cabuz\SafeMode\Guards;

use Cabuz\SafeMode\Exceptions\QueueHaltedException;
use Cabuz\SafeMode\Exceptions\SchedulerHaltedException;
use Cabuz\SafeMode\Support\SafeMode;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Refuses to start queue workers and the scheduler when the mode halts them.
 * Stopping the command itself also covers `queue:work --once`, which never
 * enters the worker loop.
 */
class CommandGuard
{
    public const QUEUE_COMMANDS = ['queue:work', 'queue:listen', 'horizon', 'horizon:work'];

    public const SCHEDULER_COMMANDS = ['schedule:run', 'schedule:work'];

    public static function register(Dispatcher $events, SafeMode $guard): void
    {
        $events->listen(CommandStarting::class, function (CommandStarting $event) use ($guard) {
            $command = (string) $event->command;

            if ($guard->haltsQueue() && in_array($command, self::QUEUE_COMMANDS, true)) {
                throw QueueHaltedException::forCommand($command);
            }

            if ($guard->haltsScheduler() && in_array($command, self::SCHEDULER_COMMANDS, true)) {
                throw SchedulerHaltedException::make($command);
            }
        });
    }
}
