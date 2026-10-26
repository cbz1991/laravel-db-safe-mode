<?php

namespace Cabuz\SafeMode\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void engage(string $reason, bool $realData = false, string $mode = 'strict', array{block_writes?: bool, halt_queue?: bool, halt_scheduler?: bool} $settings = [], bool $explicitMode = false)
 * @method static bool isActive()
 * @method static string mode()
 * @method static bool isExplicitMode()
 * @method static bool blocksWrites()
 * @method static bool haltsQueue()
 * @method static bool haltsScheduler()
 * @method static bool masksData()
 * @method static string|null reason()
 * @method static bool isRealData()
 * @method static void markRealData()
 * @method static mixed allowWrites(\Closure $callback)
 * @method static bool writesAreAllowed()
 * @method static void reset()
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
