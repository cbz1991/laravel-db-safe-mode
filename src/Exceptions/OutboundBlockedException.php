<?php

namespace Cabuz\SafeMode\Exceptions;

use RuntimeException;

class OutboundBlockedException extends RuntimeException
{
    public static function forHost(string $host): self
    {
        return new self(
            "[safe-mode] An outbound HTTP request to [{$host}] was blocked because real/production data is in use. "
            .'Add the host to safe-mode.outbound.http.allowed_hosts if it is safe.'
        );
    }
}
