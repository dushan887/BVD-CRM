<?php

declare(strict_types=1);

namespace BVD\CRM\Models;

final class Client extends AbstractModel {

    protected static string $table;
    private   static string $aliasTable;

    public static function boot( string $prefix ): void {
        self::$table      = "{$prefix}bvd_clients";
        self::$aliasTable = "{$prefix}bvd_client_aliases";
    }

    /* ---------- PUBLIC API ---------- */

    /** Resolve $name via alias → client‑id, fall back to real name, or create. */
    public static function upsertByName( string $name ): int {

        // 1) alias?
        $id = (int) self::db()->get_var(
            self::db()->prepare(
                "SELECT client_id FROM " . self::$aliasTable . " WHERE alias = %s",
                $name
            )
        );
        if ( $id ) {
            return $id;
        }

        // 2) canonical name?
        $id = (int) self::db()->get_var(
            self::db()->prepare(
                "SELECT id FROM " . self::$table . " WHERE name = %s",
                $name
            )
        );
        if ( $id ) {
            return $id;
        }

        // 3) brand‑new – insert as a **real client**, do NOT create alias
        return self::insert( [ 'name' => $name ] );
    }

    /** Store another spelling for an existing client‑id. */
    public static function addAlias( int $clientId, string $alias ): void {

        self::db()->query(
            self::db()->prepare(
                "INSERT IGNORE INTO " . self::$aliasTable . " (client_id, alias)
                 VALUES ( %d, %s )",
                $clientId,
                $alias
            )
        );
    }
}
