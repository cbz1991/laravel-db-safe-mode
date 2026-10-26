<?php

namespace Cabuz\SafeMode\Exceptions;

use RuntimeException;

class SchedulerHaltedException extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            '[safe-mode] schedule:run was aborted because real/production data is in use. '
            .'Run the specific command you need by hand instead.'
        );
    }
}
