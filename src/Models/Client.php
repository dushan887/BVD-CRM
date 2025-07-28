<?php

declare(strict_types=1);

namespace BVD\CRM\Models;

final class Client extends AbstractModel
{
    protected static string $table;

    public static function boot(string $prefix): void
    {
        self::$table = "{$prefix}bvd_clients";
    }

    public static function upsertByName(string $name): int
    {
        $id = (int) self::db()->get_var(
            self::db()->prepare("SELECT id FROM " . self::$table . " WHERE name = %s", $name)
        );

        if ($id) {
            return $id;
        }

        return self::insert(['name' => $name]);
    }
}
