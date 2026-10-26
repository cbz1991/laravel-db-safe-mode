<?php

namespace Cabuz\SafeMode\Support;

use Closure;

/**
 * Central state for the package: whether it is engaged, in which mode, why,
 * and the temporary escape hatch that lets intentional writes through.
 */
class SafeMode
{
    public const STRICT = 'strict';

    public const DEBUG = 'debug';

    protected bool $active = false;

    protected ?string $reason = null;

    protected bool $realData = false;

    protected string $mode = self::STRICT;

    /** Whether the mode was set explicitly (SAFE_MODE) rather than derived from the data. */
    protected bool $explicitMode = false;

    /** @var array{block_writes: bool, halt_queue: bool, halt_scheduler: bool} */
    protected array $settings = [
        'block_writes' => true,
        'halt_queue' => true,
        'halt_scheduler' => true,
    ];

    /** Depth counter so allowWrites() calls can nest safely. */
    protected int $writesAllowed = 0;

    /**
     * @param  array{block_writes?: bool, halt_queue?: bool, halt_scheduler?: bool}  $settings
     */
    public function engage(
        string $reason,
        bool $realData = false,
        string $mode = self::STRICT,
        array $settings = [],
        bool $explicitMode = false,
    ): void {
        $this->active = true;
        $this->reason = $reason;
        $this->realData = $this->realData || $realData;
        $this->mode = $mode;
        $this->explicitMode = $explicitMode;
        $this->settings = array_merge($this->settings, $settings);
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function isExplicitMode(): bool
    {
        return $this->explicitMode;
    }

    public function blocksWrites(): bool
    {
        return $this->active && $this->settings['block_writes'];
    }

    public function haltsQueue(): bool
    {
        return $this->active && $this->settings['halt_queue'];
    }

    public function haltsScheduler(): bool
    {
        return $this->active && $this->settings['halt_scheduler'];
    }

    /**
     * PII is masked when there is something to protect: real data was
     * detected, or the mode was chosen explicitly. Seeded local data isn't.
     */
    public function masksData(): bool
    {
        return $this->active && ($this->realData || $this->explicitMode);
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function isRealData(): bool
    {
        return $this->realData;
    }

    public function markRealData(): void
    {
        $this->realData = true;
    }

    /**
     * Run a callback with database writes temporarily permitted. Use this to
     * wrap writes you actually mean to perform while the guard is on.
     *
     * @template TReturn
     *
     * @param  Closure():TReturn  $callback
     * @return TReturn
     */
    public function allowWrites(Closure $callback)
    {
        $this->pushAllowWrites();

        try {
            return $callback();
        } finally {
            $this->popAllowWrites();
        }
    }

    /** Begin a region where writes are permitted (balance with popAllowWrites). */
    public function pushAllowWrites(): void
    {
        $this->writesAllowed++;
    }

    public function popAllowWrites(): void
    {
        $this->writesAllowed = max(0, $this->writesAllowed - 1);
    }

    public function writesAreAllowed(): bool
    {
        return $this->writesAllowed > 0;
    }

    /**
     * Reset the transient per-request state. Called between requests, tasks
     * and ticks on long-lived runtimes (Octane) so a write permission can
     * never leak from one unit of work into the next. The environment-derived
     * state (active, mode, reason, real-data) is intentionally preserved: it
     * does not change between requests within the same worker process.
     */
    public function reset(): void
    {
        $this->writesAllowed = 0;
    }
}
