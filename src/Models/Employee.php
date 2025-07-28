<?php

declare(strict_types=1);

namespace BVD\CRM\Models;

final class Employee extends AbstractModel
{
    protected static string $table;

    public static function boot(string $prefix): void
    {
        self::$table = "{$prefix}bvd_employees";
    }

    public static function idByName(string $name): ?int
    {
        return (int) self::db()->get_var(
            self::db()->prepare("SELECT id FROM " . self::$table . " WHERE name = %s", $name)
        ) ?: null;
    }

    public static function upsert(string $name): int
    {
        $id = self::idByName($name);
        if ($id) {
            return $id;
        }

        return self::insert(['name' => $name, 'slug' => sanitize_title($name)]);
    }
}
