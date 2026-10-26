<?php

namespace Cabuz\SafeMode\Guards;

use Cabuz\SafeMode\Exceptions\WriteBlockedException;
use Cabuz\SafeMode\Support\SafeMode;
use Closure;

/**
 * Overrides the write entry points on a database Connection so that, while
 * the guard is engaged, any mutation throws unless explicitly allowed.
 *
 * Reads pass through. Every query is also checked in run(), so a write sent
 * through a read method (e.g. PostgreSQL's "insert ... returning id" via
 * selectFromWriteConnection()) is caught too. Intentional writes go through
 * SafeMode::allowWrites(). This is the piece that stops a forgotten job or
 * cron from flipping a flag on a real user.
 */
trait GuardsWrites
{
    protected function safeMode(): SafeMode
    {
        return app(SafeMode::class);
    }

    protected function guardWrite(string $query, bool $allowInfrastructureTables = true): void
    {
        $guard = $this->safeMode();

        if (! $guard->isActive() || $guard->writesAreAllowed()) {
            return;
        }

        if ($allowInfrastructureTables && $this->targetsAllowedTable($query)) {
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
     * Only data-mutating SQL is blocked. Reads, schema changes (migrations),
     * transaction control and session/pragma statements pass through.
     */
    protected function guardDmlStatement(string $query): void
    {
        $sql = strtolower((string) preg_replace('/^(?:\s+|\/\*.*?\*\/|--[^\n]*)*/s', '', $query));

        // Several statements in one call ("select 1; delete from users"): a
        // write anywhere counts, and the infrastructure-table allowlist doesn't
        // apply, since it only looks at the first statement.
        if (preg_match('/;\s*(?:\/\*.*?\*\/\s*)*(?:insert|update|delete|replace|merge|truncate|upsert)\b/s', $sql)) {
            $this->guardWrite($query, allowInfrastructureTables: false);

            return;
        }

        if ($this->isDataModifyingQuery($sql)) {
            $this->guardWrite($query);
        }
    }

    /** Expects lower-cased SQL with leading comments stripped. */
    protected function isDataModifyingQuery(string $sql): bool
    {
        foreach (['insert', 'update', 'delete', 'replace', 'merge', 'truncate', 'upsert'] as $dml) {
            if (str_starts_with($sql, $dml)) {
                return true;
            }
        }

        // A CTE can wrap a write: "with x as (...) update ...". Row locks
        // ("for update") are reads and must not count.
        if (str_starts_with($sql, 'with')) {
            $sql = preg_replace('/\bfor\s+(?:no\s+key\s+)?update\b/', '', $sql);

            return (bool) preg_match('/\b(?:insert\s+into|update\s+[`"\[]?\w|delete\s+from|merge\s+into)/', $sql);
        }

        return false;
    }

    /**
     * Every query, whichever public method sent it, is executed through run().
     * Guarding here rather than in insert()/update()/... also avoids
     * overriding methods whose signatures differ per driver and Laravel
     * version (MySqlConnection::insert() gained a $sequence parameter in 11).
     *
     * @param  string  $query
     * @param  array<array-key, mixed>  $bindings
     */
    protected function run($query, $bindings, Closure $callback)
    {
        $this->guardDmlStatement($query);

        return parent::run($query, $bindings, $callback);
    }
}
