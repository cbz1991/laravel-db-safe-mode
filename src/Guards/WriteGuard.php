<?php

namespace Cabuz\SafeMode\Guards;

use Illuminate\Database\Connection;

/**
 * Registers guarded connection resolvers per driver. The ConnectionFactory
 * calls Connection::getResolver($driver) before its own match(), so returning
 * a guarded subclass here transparently swaps in our write protection.
 */
class WriteGuard
{
    /** @var array<string, class-string<Connection>> */
    protected static array $map = [
        'mysql' => GuardedMySqlConnection::class,
        'mariadb' => GuardedMariaDbConnection::class,
        'pgsql' => GuardedPostgresConnection::class,
        'sqlite' => GuardedSQLiteConnection::class,
        'sqlsrv' => GuardedSqlServerConnection::class,
    ];

    public static function register(): void
    {
        foreach (static::$map as $driver => $connectionClass) {
            Connection::resolverFor($driver, function ($pdo, $database, $prefix, $config) use ($connectionClass) {
                return new $connectionClass($pdo, $database, $prefix, $config);
            });
        }
    }
}
