<?php

declare(strict_types=1);

namespace BVD\CRM\Models;

final class Timesheet extends AbstractModel
{
    protected static string $orderBy = 'work_date';

    public static function boot(string $prefix): void
    {
        self::$table = "{$prefix}bvd_timesheets";
    }

    /** @param array<string,mixed> $row */
    public static function upsert(array $row): void
    {
        $db = self::db();

        // Uniqueness = client + job + employee + work_date
        $id = (int) $db->get_var(
            $db->prepare(
                "SELECT id FROM " . self::$table . "
                 WHERE client_id=%d AND job_id=%d AND employee_id=%d AND work_date=%s",
                $row['client_id'],
                $row['job_id'],
                $row['employee_id'],
                $row['work_date']
            )
        );

        if ($id) {
            self::update($id, ['hours' => $row['hours']]);
            return;
        }

        self::insert($row);
    }
}
