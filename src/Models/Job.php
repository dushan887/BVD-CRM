<?php

declare(strict_types=1);

namespace BVD\CRM\Models;

final class Job extends AbstractModel
{
    protected static string $table;

    public static function boot(string $prefix): void
    {
        self::$table = "{$prefix}bvd_jobs";
    }

    public static function upsert(string $code, string $title, array $extra = []): int
    {
        $db = self::db();

        if ($code) {
            $id = (int) $db->get_var(
                $db->prepare("SELECT id FROM " . self::$table . " WHERE job_code = %s", $code)
            );
            if ($id) {
                self::update($id, array_merge(['title' => $title], $extra));
                return $id;
            }
        }

        // Fallback by title if no code.
        $id = (int) $db->get_var(
            $db->prepare("SELECT id FROM " . self::$table . " WHERE job_code IS NULL AND title = %s", $title)
        );
        if ($id) {
            self::update($id, $extra);
            return $id;
        }

        return self::insert(array_merge(
            ['job_code' => $code ?: null, 'title' => $title],
            $extra
        ));
    }
}
