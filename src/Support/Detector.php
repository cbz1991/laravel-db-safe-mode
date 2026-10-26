<?php

namespace Cabuz\SafeMode\Support;

use Illuminate\Contracts\Foundation\Application;
use Throwable;

/**
 * Decides whether the guard should engage and whether real data is present.
 */
class Detector
{
    public function __construct(
        protected Application $app,
        protected array $config,
    ) {}

    /**
     * Should the package engage at all? Off in production, on everywhere else
     * unless explicitly overridden.
     */
    public function shouldEngage(): bool
    {
        $enabled = $this->config['enabled'] ?? null;

        if ($enabled !== null) {
            return (bool) $enabled;
        }

        $prodEnvs = (array) ($this->config['production_environments'] ?? ['production']);

        return ! $this->app->environment($prodEnvs);
    }

    /**
     * Peek at one column to decide if the data looks like a real dump rather
     * than seeded/anonymized data. Cheap, best-effort, never throws.
     */
    public function hasRealData(): bool
    {
        $cfg = $this->config['detect_real_data'] ?? [];

        if (! ($cfg['enabled'] ?? false)) {
            return false;
        }

        $suffix = $cfg['anonymized_suffix'] ?? '@example.com';
        $table = $cfg['table'] ?? 'users';
        $column = $cfg['column'] ?? 'email';

        try {
            $connection = $this->app['db']->connection($cfg['connection'] ?? null);

            // A single row whose value is NOT anonymized means real data.
            $realRow = $connection->table($table)
                ->whereNotNull($column)
                ->where($column, 'not like', '%'.$suffix)
                ->limit(1)
                ->exists();

            return $realRow;
        } catch (Throwable) {
            // No DB, missing table, etc. — don't guess, don't crash boot.
            return false;
        }
    }
}
