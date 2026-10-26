<?php

namespace Cabuz\SafeMode\Exceptions;

use RuntimeException;

class WriteBlockedException extends RuntimeException
{
    public static function forQuery(string $query): self
    {
        $preview = str($query)->squish()->limit(120)->value();

        return new self(
            "[safe-mode] A database write was blocked because real/production data is in use:\n  {$preview}\n"
            .'If this write is intentional, wrap it in SafeMode::allowWrites(fn () => ...).'
        );
    }
}
