<?php

declare(strict_types=1);

namespace BVD\CRM\Models;

use wpdb;


abstract class AbstractModel
{
    protected static string $table;
    protected static string $pk = 'id';
    protected static string $orderBy = 'name';

    protected static function db(): wpdb
    {
        global $wpdb;
        return $wpdb;
    }

    public static function find(int $id): ?array
    {
        return self::db()->get_row(
            self::db()->prepare("SELECT * FROM " . static::$table . " WHERE " . static::$pk . " = %d", $id),
            ARRAY_A
        ) ?: null;
    }

    public static function all(): array
    {
        return self::db()->get_results(
            "SELECT * FROM " . static::$table . " ORDER BY " . static::$orderBy,
            ARRAY_A
        );
    }

    /** @param array<string,mixed> $data */
    public static function insert(array $data): int
    {
        self::db()->insert(static::$table, $data);
        return (int) self::db()->insert_id;
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        self::db()->update(static::$table, $data, [static::$pk => $id]);
    }
}
