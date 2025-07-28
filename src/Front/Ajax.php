<?php

declare(strict_types=1);

namespace BVD\CRM\Front;

use BVD\CRM\Models\Client;
use BVD\CRM\Models\Timesheet;
use BVD\CRM\Models\Employee;
use BVD\CRM\Models\Job;
use BVD\CRM\Helpers\Date;

final class Ajax
{
    public function register(): void
    {
        add_action('wp_ajax_nopriv_bvd_crm_client_summary', [$this, 'summary']);
        add_action('wp_ajax_bvd_crm_client_summary',        [$this, 'summary']);

        add_action('wp_ajax_nopriv_bvd_crm_client_tasks',   [$this, 'tasks']);
        add_action('wp_ajax_bvd_crm_client_tasks',          [$this, 'tasks']);

        add_action('wp_ajax_nopriv_bvd_crm_task_details',   [$this, 'taskDetails']);
        add_action('wp_ajax_bvd_crm_task_details',          [$this, 'taskDetails']);

        add_action('wp_ajax_nopriv_bvd_crm_nonce', [$this, 'nonce']);
        add_action('wp_ajax_bvd_crm_nonce',        [$this, 'nonce']);
    }

    /* --------------------------------------------------------------------- */
    /*  SUMMARY TABLE                                                        */
    /* --------------------------------------------------------------------- */

    public function summary(): void
    {
        check_ajax_referer('bvd_crm_front', 'nonce');

        global $wpdb;
        Client::boot($wpdb->prefix);
        Timesheet::boot($wpdb->prefix);

        [$whereSql, $params] = $this->periodWhere(
            $_GET['period_type'] ?? 'month',
            $_GET['value']       ?? null
        );

        $sql = "
            SELECT c.id, c.name,
                   SUM(t.hours) AS total,
                   SUM(CASE WHEN t.maintenance=0 AND t.internal=0
                               AND (j.classification IS NULL OR j.classification='' OR j.classification='-')
                            THEN t.hours ELSE 0 END) AS default_billable,
                   SUM(CASE WHEN t.maintenance=0 AND t.internal=0
                               AND (j.classification IS NOT NULL AND j.classification!='' AND j.classification!='-')
                            THEN t.hours ELSE 0 END) AS project_billable,
                   SUM(CASE WHEN (t.maintenance=1 OR t.internal=1) THEN t.hours ELSE 0 END) AS non_billable,
                   c.monthly_limit, c.quarterly_limit
            FROM {$wpdb->prefix}bvd_timesheets t
            JOIN {$wpdb->prefix}bvd_clients c ON c.id=t.client_id
            JOIN {$wpdb->prefix}bvd_jobs    j ON j.id=t.job_id
            WHERE 1=1 $whereSql
            GROUP BY c.id
            ORDER BY c.name
        ";
        $summary = $wpdb->get_results($wpdb->prepare($sql, ...$params));

        wp_send_json_success($summary);
    }

    /* --------------------------------------------------------------------- */
    /*  EXPANDABLE ROW – TASK BUCKETS                                        */
    /* --------------------------------------------------------------------- */

    public function tasks(): void
    {
        check_ajax_referer('bvd_crm_front', 'nonce');

        global $wpdb;
        Timesheet::boot($wpdb->prefix);

        $client = (int) ($_GET['client'] ?? 0);
        if (!$client) {
            wp_send_json_error('Missing client', 400);
        }

        [$whereSql, $params] = $this->periodWhere(
            $_GET['period_type'] ?? 'month',
            $_GET['value']       ?? null
        );

        $sql = "
            SELECT t.job_id, j.title, j.classification,
                   SUM(t.hours) AS total,
                   SUM(CASE WHEN t.maintenance=1 OR t.internal=1 THEN t.hours ELSE 0 END) AS non_billable,
                   SUM(CASE WHEN t.maintenance=0 AND t.internal=0
                               AND (j.classification IS NULL OR j.classification='' OR j.classification='-')
                            THEN t.hours ELSE 0 END) AS default_billable,
                   SUM(CASE WHEN t.maintenance=0 AND t.internal=0
                               AND (j.classification IS NOT NULL AND j.classification!='' AND j.classification!='-')
                            THEN t.hours ELSE 0 END) AS project_billable
            FROM {$wpdb->prefix}bvd_timesheets t
            JOIN {$wpdb->prefix}bvd_jobs j ON j.id=t.job_id
            WHERE t.client_id=%d $whereSql
            GROUP BY t.job_id
            ORDER BY j.title
        ";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $client, ...$params));

        /* ---- optional per‑employee breakdown when admin ------------------ */
        if (current_user_can('administrator') && $rows) {
            Employee::boot($wpdb->prefix);
            $jobIds = wp_list_pluck($rows, 'job_id');
            $place  = implode(',', array_fill(0, count($jobIds), '%d'));

            $extra = $wpdb->get_results($wpdb->prepare(
                "
                SELECT t.job_id, e.name, SUM(t.hours) AS hrs
                FROM {$wpdb->prefix}bvd_timesheets t
                JOIN {$wpdb->prefix}bvd_employees e ON e.id=t.employee_id
                WHERE t.client_id=%d AND t.job_id IN($place) $whereSql
                GROUP BY t.job_id, e.id
                ",
                array_merge([$client], $jobIds, $params)
            ), ARRAY_A);

            $map = [];
            foreach ($extra as $e) {
                $map[$e['job_id']][] = ['emp' => $e['name'], 'hrs' => (float) $e['hrs']];
            }
            foreach ($rows as &$r) {
                $r->employees = $map[$r->job_id] ?? [];
            }
        }

        wp_send_json_success($rows);
    }

    /* --------------------------------------------------------------------- */
    /*  MODAL – TASK DETAILS ACROSS CLIENTS                                  */
    /* --------------------------------------------------------------------- */

    public function taskDetails(): void
    {
        check_ajax_referer('bvd_crm_front', 'nonce');

        global $wpdb;
        Timesheet::boot($wpdb->prefix);

        $job = (int) ($_GET['job'] ?? 0);
        if (!$job) {
            wp_send_json_error('Missing job', 400);
        }

        [$whereSql, $params] = $this->periodWhere(
            $_GET['period_type'] ?? 'month',
            $_GET['value']       ?? null
        );

        $selectEmp = current_user_can('administrator')
            ? 'e.name  AS employee,'
            : "'Total' AS employee,";

        $groupEmp  = current_user_can('administrator') ? ', e.id' : '';

        $sql = "
            SELECT c.name,
                   $selectEmp
                   SUM(t.hours) AS hrs
            FROM {$wpdb->prefix}bvd_timesheets t
            JOIN {$wpdb->prefix}bvd_clients    c ON c.id=t.client_id
            JOIN {$wpdb->prefix}bvd_employees  e ON e.id=t.employee_id
            WHERE t.job_id=%d $whereSql
            GROUP BY c.id $groupEmp
            ORDER BY c.name
        ";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $job, ...$params));

        wp_send_json_success($rows);
    }

    /* --------------------------------------------------------------------- */
    /*  NONCE REFRESH                                                        */
    /* --------------------------------------------------------------------- */

    public function nonce(): void
    {
        wp_send_json_success(wp_create_nonce('bvd_crm_front'));
    }

    /* --------------------------------------------------------------------- */
    /*  Helper – build WHERE for month / quarter                             */
    /* --------------------------------------------------------------------- */

    private function periodWhere(string $type = 'month', ?string $value = null): array
    {
        global $wpdb;

        if ($type === 'quarter') {
            if (!preg_match('/^(\d{4})-Q([1-4])$/', $value ?? '', $m)) {
                $value = Date::quarterFromDate(current_time('mysql'));
                preg_match('/^(\d{4})-Q([1-4])$/', $value, $m);
            }
            [$year, $q] = [(int) $m[1], (int) $m[2]];
            $months = range(($q - 1) * 3 + 1, $q * 3);
            return [
                "AND YEAR(work_date)=%d AND MONTH(work_date) IN (" . implode(',', array_fill(0, 3, '%d')) . ")",
                array_merge([$year], $months)
            ];
        }

        // default = month
        if (!preg_match('/^(\d{4})-(\d{2})$/', $value ?? '', $m)) {
            $value = Date::monthFromDate(current_time('mysql'));
            preg_match('/^(\d{4})-(\d{2})$/', $value, $m);
        }
        return [
            "AND YEAR(work_date)=%d AND MONTH(work_date)=%d",
            [(int) $m[1], (int) $m[2]]
        ];
    }
}
