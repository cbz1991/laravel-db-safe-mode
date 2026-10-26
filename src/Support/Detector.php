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
     * The configured SAFE_MODE or, when unset, "strict" on real data and
     * "debug" otherwise. An unknown value falls back to "strict".
     */
    public function mode(bool $realData): string
    {
        if (! $this->modeIsExplicit()) {
            return $realData ? SafeMode::STRICT : SafeMode::DEBUG;
        }

        $configured = strtolower(trim((string) $this->config['mode']));

        return $configured === SafeMode::DEBUG ? SafeMode::DEBUG : SafeMode::STRICT;
    }

    public function modeIsExplicit(): bool
    {
        $configured = $this->config['mode'] ?? null;

        return $configured !== null && trim((string) $configured) !== '';
    }

    /**
     * @return array{block_writes: bool, halt_queue: bool, halt_scheduler: bool}
     */
    public function modeSettings(string $mode): array
    {
        $defaults = $mode === SafeMode::DEBUG
            ? ['block_writes' => false, 'halt_queue' => false, 'halt_scheduler' => true]
            : ['block_writes' => true, 'halt_queue' => true, 'halt_scheduler' => true];

        $configured = (array) ($this->config['modes'][$mode] ?? []);

        $settings = [];
        foreach ($defaults as $key => $default) {
            $settings[$key] = (bool) ($configured[$key] ?? $default);
        }

        // Older published configs have global switches instead of modes;
        // an explicit false there still turns that guard off.
        if (($this->config['writes']['block'] ?? null) === false) {
            $settings['block_writes'] = false;
        }
        if (($this->config['queue']['halt'] ?? null) === false) {
            $settings['halt_queue'] = false;
        }
        if (($this->config['scheduler']['halt'] ?? null) === false) {
            $settings['halt_scheduler'] = false;
        }

        return $settings;
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
