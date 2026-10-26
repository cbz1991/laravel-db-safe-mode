<?php

namespace Cabuz\SafeMode\Support;

use Illuminate\Contracts\Foundation\Application;
use Throwable;

/**
 * Decides whether the guard should engage and whether real data is present.
 */
class Detector
{
    /**
     * @param  array<string, mixed>  $config  the safe-mode config
     */
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

        $table = $cfg['table'] ?? 'users';
        $column = $cfg['column'] ?? 'email';

        try {
            $connection = $this->app['db']->connection($cfg['connection'] ?? null);

            // Check first rather than let the query fail: on PostgreSQL a failed
            // query aborts the surrounding transaction (e.g. in a test suite).
            if (! $connection->getSchemaBuilder()->hasColumn($table, $column)) {
                return false;
            }

            $query = $connection->table($table)->whereNotNull($column);

            // A single row whose value is NOT anonymized means real data.
            foreach ($this->anonymizedSuffixes($cfg) as $suffix) {
                $query->where($column, 'not like', '%'.$suffix);
            }

            return $query->limit(1)->exists();
        } catch (Throwable) {
            // No DB, missing table, etc. — don't guess, don't crash boot.
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $cfg  the detect_real_data config
     * @return list<string>
     */
    protected function anonymizedSuffixes(array $cfg): array
    {
        $domains = (array) ($cfg['anonymized_domains'] ?? ['example.com', 'example.org', 'example.net']);

        $suffixes = array_map(fn ($domain) => '@'.ltrim((string) $domain, '@'), $domains);

        // Configs published before 1.2.3 only have a single suffix.
        if (isset($cfg['anonymized_suffix'])) {
            $suffixes[] = (string) $cfg['anonymized_suffix'];
        }

        return array_values(array_unique($suffixes));
    }
}
