<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support;

use Closure;
use Throwable;
use RuntimeException;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Runs a callback with foreign-key checks off on the default connection,
 * toggling only on the outermost of nested calls. SeederExecutor wraps a
 * bundle in it when the bundle asks.
 *
 * MySQL, MariaDB and SQLite switch foreign keys off outright. PostgreSQL cannot
 * switch off *only* foreign keys, so its behaviour follows
 * `laranail.package-tools.seeders.postgres_foreign_keys`:
 *
 * - `defer` (default): `SET CONSTRAINTS ALL DEFERRED` -- only DEFERRABLE
 *   constraints are relaxed; an ordinary foreign key is still enforced.
 * - `replica`: `SET session_replication_role = replica` -- foreign keys really
 *   are off, and so is every ordinary trigger while the bundle runs. Needs a
 *   superuser or a PostgreSQL 15+ grant; without it the run fails loudly.
 *
 * This deliberately mirrors laranail/db-tools' ForeignKeySwitch rather than
 * using it: db-tools requires this package, so the reverse dependency would be
 * a cycle.
 */
final class ForeignKeyCheckGuard
{
    public const string DEFER = 'defer';

    public const string REPLICA = 'replica';

    private int $depth = 0;

    private bool $replica = false;

    public static function postgresMode(): string
    {
        $mode = config('laranail.package-tools.seeders.postgres_foreign_keys', self::DEFER);

        if (! in_array($mode, [self::DEFER, self::REPLICA], true)) {
            throw new InvalidArgumentException(sprintf(
                "laranail.package-tools.seeders.postgres_foreign_keys must be '%s' or '%s', got %s.",
                self::DEFER,
                self::REPLICA,
                var_export($mode, true),
            ));
        }

        return $mode;
    }

    /**
     * @template TReturn
     *
     * @param Closure(): TReturn $callback
     *
     * @return TReturn
     */
    public function run(Closure $callback): mixed
    {
        $this->depth++;
        if ($this->depth === 1) {
            $this->disable();
        }

        try {
            return $callback();
        } finally {
            $this->depth--;
            if ($this->depth === 0) {
                $this->enable();
            }
        }
    }

    public function depth(): int
    {
        return $this->depth;
    }

    private function disable(): void
    {
        $this->replica = DB::connection()->getDriverName() === 'pgsql' && self::postgresMode() === self::REPLICA;

        if (! $this->replica) {
            Schema::disableForeignKeyConstraints();

            return;
        }

        try {
            DB::statement('SET session_replication_role = replica');
        } catch (Throwable $e) {
            $this->replica = false;
            $this->depth = 0;

            throw new RuntimeException('PostgreSQL refused `SET session_replication_role = replica`, which '
            . "laranail.package-tools.seeders.postgres_foreign_keys = 'replica' needs. Grant the role SET on "
            . "session_replication_role (PostgreSQL 15+) or use a superuser, or set the mode back to 'defer'.", $e->getCode(), previous: $e);
        }
    }

    private function enable(): void
    {
        if ($this->replica) {
            DB::statement('SET session_replication_role = DEFAULT');
            $this->replica = false;

            return;
        }

        Schema::enableForeignKeyConstraints();
    }
}
