<?php

namespace Cabuz\SafeMode\Exceptions;

use RuntimeException;

class QueueHaltedException extends RuntimeException
{
    public static function forCommand(string $command): self
    {
        return new self(
            "[safe-mode] {$command} was refused: strict mode doesn't run queued jobs. "
            .'Set SAFE_MODE=debug to run them with outbound mail, notifications and HTTP still blocked.'
        );
    }
}
