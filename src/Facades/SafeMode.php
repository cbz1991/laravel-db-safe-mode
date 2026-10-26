<?php

namespace Cabuz\SafeMode\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void engage(string $reason, bool $realData = false)
 * @method static bool isActive()
 * @method static string|null reason()
 * @method static bool isRealData()
 * @method static void markRealData()
 * @method static mixed allowWrites(\Closure $callback)
 * @method static bool writesAreAllowed()
 *
 * @see \Cabuz\SafeMode\Support\SafeMode
 */
class SafeMode extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Cabuz\SafeMode\Support\SafeMode::class;
    }
}
