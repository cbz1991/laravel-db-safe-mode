<?php

namespace Cabuz\SafeMode\Commands;

use Cabuz\SafeMode\Masking\Maskers;
use Cabuz\SafeMode\Support\SafeMode;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Permanently rewrite PII in a LOCAL copy of a production dump. This mutates
 * the database, so it refuses to run against real data over a live connection
 * and against the configured production environments.
 */
class AnonymizeCommand extends Command
{
    protected $signature = 'safe-mode:anonymize
        {--connection= : The database connection to operate on}
        {--chunk=500 : Rows updated per batch}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Anonymize PII in a local database copy using the configured masking map';

    public function handle(SafeMode $guard, Maskers $maskers): int
    {
        $connectionName = $this->option('connection') ?: config('database.default');
        $connection = DB::connection($connectionName);

        $map = (array) config('safe-mode.masking.attributes', []);

        if ($map === []) {
            $this->components->error('No attributes configured in safe-mode.masking.attributes — nothing to anonymize.');

            return self::FAILURE;
        }

        if ($this->getLaravel()->environment(config('safe-mode.production_environments', ['production']))) {
            $this->components->error('Refusing to anonymize: this is a production environment.');

            return self::FAILURE;
        }

        $this->components->warn("This rewrites data in the [{$connectionName}] connection. It cannot be undone.");

        if (! $this->option('force') && ! $this->confirm('Proceed?')) {
            return self::FAILURE;
        }

        // Group attributes by table isn't known generically; we assume the
        // configured attributes map to columns on the `users` table unless the
        // key is given as "table.column".
        $grouped = [];
        foreach ($map as $attribute => $masker) {
            [$table, $column] = str_contains($attribute, '.')
                ? explode('.', $attribute, 2)
                : ['users', $attribute];

            $grouped[$table][$column] = $masker;
        }

        // Writes performed here are intentional.
        $guard->allowWrites(function () use ($connection, $grouped, $maskers) {
            foreach ($grouped as $table => $columns) {
                $this->anonymizeTable($connection, $table, $columns, $maskers);
            }
        });

        $this->components->info('Anonymization complete.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string|Closure>  $columns  column => masker
     */
    protected function anonymizeTable(Connection $connection, string $table, array $columns, Maskers $maskers): void
    {
        $key = $this->primaryKey($connection, $table);
        $chunk = max(1, (int) $this->option('chunk'));
        $count = 0;

        $connection->table($table)->orderBy($key)->chunkById($chunk, function ($rows) use ($connection, $table, $columns, $maskers, $key, &$count) {
            foreach ($rows as $row) {
                $updates = [];

                // Build a lightweight object so key-aware maskers work.
                $pseudo = new class($row->{$key})
                {
                    public function __construct(private mixed $key) {}

                    public function getKey(): mixed
                    {
                        return $this->key;
                    }
                };

                foreach ($columns as $column => $masker) {
                    if (! property_exists($row, $column) || $row->{$column} === null) {
                        continue;
                    }

                    $updates[$column] = $maskers->apply($masker, $row->{$column}, $pseudo);
                }

                if ($updates !== []) {
                    $connection->table($table)->where($key, $row->{$key})->update($updates);
                    $count++;
                }
            }
        }, $key);

        $this->components->info("  {$table}: {$count} rows anonymized");
    }

    protected function primaryKey(Connection $connection, string $table): string
    {
        foreach (['id', 'uuid', 'ulid'] as $candidate) {
            if ($connection->getSchemaBuilder()->hasColumn($table, $candidate)) {
                return $candidate;
            }
        }

        return 'id';
    }
}
