<?php

namespace Cabuz\SafeMode\Support;

use Closure;

/**
 * Central state for the package: whether it is engaged, why, and the
 * temporary escape hatch that lets intentional writes through.
 */
class SafeMode
{
    protected bool $active = false;

    protected ?string $reason = null;

    protected bool $realData = false;

    /** Depth counter so allowWrites() calls can nest safely. */
    protected int $writesAllowed = 0;

    public function engage(string $reason, bool $realData = false): void
    {
        $this->active = true;
        $this->reason = $reason;
        $this->realData = $this->realData || $realData;
    }

    public function isActive(): bool
    {
        return $this->active;
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
}
