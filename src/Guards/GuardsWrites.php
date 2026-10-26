<?php

namespace Cabuz\SafeMode\Guards;

use Cabuz\SafeMode\Exceptions\WriteBlockedException;
use Cabuz\SafeMode\Support\SafeMode;

/**
 * Overrides every write entry point on a database Connection so that, while
 * the guard is engaged, any mutation throws unless explicitly allowed.
 *
 * Reads (select) are untouched. Intentional writes go through
 * SafeMode::allowWrites(). This is the piece that stops a forgotten job or
 * cron from flipping a flag on a real user.
 */
trait GuardsWrites
{
    protected function safeMode(): SafeMode
    {
        return app(SafeMode::class);
    }

    protected function guardWrite(string $query): void
    {
        $guard = $this->safeMode();

        if (! $guard->isActive() || $guard->writesAreAllowed()) {
            return;
        }

        if ($this->targetsAllowedTable($query)) {
            return;
        }

        throw WriteBlockedException::forQuery($query);
    }

    /**
     * Infrastructure tables (migrations, jobs, cache, sessions, …) are always
     * writable: they hold no real-user data and blocking them would break the
     * framework itself.
     */
    protected function targetsAllowedTable(string $query): bool
    {
        $allowed = (array) config('safe-mode.writes.allow_tables', []);

        if ($allowed === []) {
            return false;
        }

        if (! preg_match(
            '/^\s*(?:insert\s+(?:ignore\s+)?into|replace\s+into|update(?:\s+or\s+\w+)?|delete\s+from|truncate(?:\s+table)?)\s+[`"\[]?([A-Za-z0-9_\.]+)[`"\]]?/i',
            $query,
            $m
        )) {
            return false;
        }

        $table = $m[1];

        // Strip schema qualifiers: `schema.table` -> `table`.
        if (str_contains($table, '.')) {
            $table = substr($table, (int) strrpos($table, '.') + 1);
        }

        // Strip the connection's table prefix so `wp_migrations` matches `migrations`.
        $prefix = $this->getTablePrefix();
        if ($prefix !== '' && str_starts_with($table, $prefix)) {
            $table = substr($table, strlen($prefix));
        }

        return in_array($table, $allowed, true);
    }

    /**
     * For the generic statement()/unprepared() entry points we only block
     * data-mutating SQL. Schema changes (migrations), transaction control and
     * session/pragma statements must pass through untouched.
     */
    protected function guardDmlStatement(string $query): void
    {
        $verb = strtolower(ltrim($query));

        foreach (['insert', 'update', 'delete', 'replace', 'merge', 'truncate', 'upsert'] as $dml) {
            if (str_starts_with($verb, $dml)) {
                $this->guardWrite($query);

                return;
            }
        }
    }

    public function insert($query, $bindings = [])
    {
        $this->guardWrite($query);

        return parent::insert($query, $bindings);
    }

    public function update($query, $bindings = [])
    {
        $this->guardWrite($query);

        return parent::update($query, $bindings);
    }

    public function delete($query, $bindings = [])
    {
        $this->guardWrite($query);

        return parent::delete($query, $bindings);
    }

    public function statement($query, $bindings = [])
    {
        $this->guardDmlStatement($query);

        return parent::statement($query, $bindings);
    }

    public function affectingStatement($query, $bindings = [])
    {
        $this->guardWrite($query);

        return parent::affectingStatement($query, $bindings);
    }

    public function unprepared($query)
    {
        $this->guardDmlStatement($query);

        return parent::unprepared($query);
    }
}
