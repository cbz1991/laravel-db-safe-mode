<?php

namespace Cabuz\SafeMode\Exceptions;

use RuntimeException;

class SchedulerHaltedException extends RuntimeException
{
    public static function make(string $command = 'schedule:run'): self
    {
        return new self(
            "[safe-mode] {$command} was refused so nothing runs behind your back. "
            .'Run the specific command you need by hand instead.'
        );
    }
}
