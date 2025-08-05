<?php
/**
 * Front‑end AJAX endpoints.
 *
 * @package BVD\CRM\Front
 */

declare(strict_types=1);

namespace BVD\CRM\Front;

use BVD\CRM\Models\{Client, Timesheet, Employee};
use BVD\CRM\Helpers\Date;

final class Ajax
{
    public function register(): void
    {
        // summary   (top table)
        add_action('wp_ajax_nopriv_bvd_crm_client_summary', [$this,'summary']);
        add_action('wp_ajax_bvd_crm_client_summary',        [$this,'summary']);

        // tasks     (expandable row)
        add_action('wp_ajax_nopriv_bvd_crm_client_tasks',   [$this,'tasks']);
        add_action('wp_ajax_bvd_crm_client_tasks',          [$this,'tasks']);

        // details   (modal per‑job)
        add_action('wp_ajax_nopriv_bvd_crm_task_details',   [$this,'taskDetails']);
        add_action('wp_ajax_bvd_crm_task_details',          [$this,'taskDetails']);

        // nonce refresh
        add_action('wp_ajax_nopriv_bvd_crm_nonce', [$this,'nonce']);
        add_action('wp_ajax_bvd_crm_nonce',        [$this,'nonce']);
        // available periods for toolbar (hide empty)
        add_action('wp_ajax_nopriv_bvd_crm_available_periods', [$this, 'availablePeriods']);
        add_action('wp_ajax_bvd_crm_available_periods',        [$this, 'availablePeriods']);
    }

    /* ─────────────────────────────────────────────────────────────
       1.  SUMMARY  (clients × period)
       ──────────────────────────────────────────────────────────── */

    public function summary(): void
    {
        check_ajax_referer('bvd_crm_front', 'nonce');

        global $wpdb;
        Client   ::boot($wpdb->prefix);
        Timesheet::boot($wpdb->prefix);

        $type  = $_GET['period_type'] ?? 'month';
        $value = $_GET['value']       ?? null;

        [$where,$params] = $this->periodWhere($type, $value);
        $rows            = $this->summaryRows($where, $params, $wpdb);

        // fallback to “latest month with data” when current is empty
        if (!$rows && $value === null) {
            $latest = $wpdb->get_var(
                "SELECT DATE_FORMAT(MAX(work_date),'%Y-%m')
                   FROM {$wpdb->prefix}bvd_timesheets"
            );
            if ($latest) {
                [$where,$params] = $this->periodWhere('month', $latest);
                $rows            = $this->summaryRows($where, $params, $wpdb);
            }
        }

        wp_send_json_success($rows);
    }

    /** Execute summary SELECT */
    private function summaryRows(string $where, array $p, \wpdb $db): array
    {
        $sql = "
            SELECT c.id, c.name,
                   SUM(t.hours) AS total,
                   SUM(CASE WHEN t.maintenance=0 AND t.internal=0
                              AND (j.classification IS NULL
                                   OR j.classification='' OR j.classification='-')
                        THEN t.hours ELSE 0 END) AS default_billable,
                   SUM(CASE WHEN t.maintenance=0 AND t.internal=0
                              AND (j.classification IS NOT NULL
                                   AND j.classification<>'' AND j.classification<>'-')
                        THEN t.hours ELSE 0 END) AS project_billable,
                   SUM(CASE WHEN t.maintenance=1 OR t.internal=1
                        THEN t.hours ELSE 0 END) AS non_billable,
                   c.monthly_limit, c.quarterly_limit
              FROM {$db->prefix}bvd_timesheets t
              JOIN {$db->prefix}bvd_clients c ON c.id=t.client_id
              JOIN {$db->prefix}bvd_jobs    j ON j.id=t.job_id
              WHERE 1=1 $where
              GROUP BY c.id
              ORDER BY c.name";
        return $db->get_results($db->prepare($sql, ...$p));
    }

    /* ─────────────────────────────────────────────────────────────
       2.  TASK BUCKETS  (expandable row)
       ──────────────────────────────────────────────────────────── */

    public function tasks(): void
    {
        check_ajax_referer('bvd_crm_front', 'nonce');

        global $wpdb;
        Timesheet::boot($wpdb->prefix);

        $client = (int) ($_GET['client'] ?? 0);
        if (!$client) wp_send_json_error('Missing client', 400);

        [$where,$p] = $this->periodWhere(
            $_GET['period_type'] ?? 'month',
            $_GET['value']       ?? null
        );

        $sql = "
            SELECT t.job_id,
                   j.job_code,
                   j.title,
                   j.classification,
                   SUM(t.hours)                                            AS total,
                   SUM(CASE WHEN t.maintenance=1 OR t.internal=1
                        THEN t.hours ELSE 0 END)                           AS non_billable,
                   SUM(CASE WHEN t.maintenance=0 AND t.internal=0
                              AND (j.classification IS NULL
                                   OR j.classification='' OR j.classification='-')
                        THEN t.hours ELSE 0 END)                           AS default_billable,
                   SUM(CASE WHEN t.maintenance=0 AND t.internal=0
                              AND (j.classification IS NOT NULL
                                   AND j.classification<>'' AND j.classification<>'-')
                        THEN t.hours ELSE 0 END)                           AS project_billable
              FROM {$wpdb->prefix}bvd_timesheets t
              JOIN {$wpdb->prefix}bvd_jobs j ON j.id=t.job_id
             WHERE t.client_id=%d $where
             GROUP BY t.job_id
             ORDER BY j.title";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $client, ...$p));

        /* per‑employee breakdown for admins */
        if (current_user_can('administrator') && $rows) {
            Employee::boot($wpdb->prefix);
            $ids   = wp_list_pluck($rows, 'job_id');
            $place = implode(',', array_fill(0, count($ids), '%d'));

            $extra = $wpdb->get_results($wpdb->prepare(
                "SELECT t.job_id, e.name, SUM(t.hours) AS hrs
                   FROM {$wpdb->prefix}bvd_timesheets t
                   JOIN {$wpdb->prefix}bvd_employees e ON e.id=t.employee_id
                  WHERE t.client_id=%d AND t.job_id IN($place) $where
                  GROUP BY t.job_id, e.id",
                array_merge([$client], $ids, $p)
            ), ARRAY_A);

            $map = [];
            foreach ($extra as $e) {
                $map[$e['job_id']][] = ['emp'=>$e['name'],'hrs'=>(float)$e['hrs']];
            }
            foreach ($rows as &$r) {
                $r->employees = $map[$r->job_id] ?? [];
            }
        }

        wp_send_json_success($rows);
    }

    /* ─────────────────────────────────────────────────────────────
       3.  MODAL  (task details across clients)
       ──────────────────────────────────────────────────────────── */
    public function taskDetails(): void {
        check_ajax_referer('bvd_crm_front', 'nonce');
        if (!current_user_can('administrator')) {
            wp_send_json_error('Forbidden', 403);
        }
        global $wpdb;
        Timesheet::boot($wpdb->prefix);
        $job = (int) ($_GET['job'] ?? 0);
        if (!$job) {
            wp_send_json_error('Missing job', 400);
        }
        $sql = "
            SELECT c.name AS clinic,
                DATE_FORMAT(t.work_date, '%%Y-%%m-%%d') AS date,
                e.name AS employee,
                SUM(t.hours) AS hrs
            FROM {$wpdb->prefix}bvd_timesheets t
            JOIN {$wpdb->prefix}bvd_clients c ON c.id = t.client_id
            JOIN {$wpdb->prefix}bvd_employees e ON e.id = t.employee_id
            WHERE t.job_id = %d
            GROUP BY c.id, t.work_date, e.id
            ORDER BY c.name ASC, t.work_date DESC, e.name ASC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $job));
        wp_send_json_success($rows);
    }


    /* ───────────────────────────────────────────────────────────── */
    public function nonce(): void
    {
        wp_send_json_success(wp_create_nonce('bvd_crm_front'));
    }
    /**
     * Return periods (month or quarter) that have data, for toolbar filtering.
     */
    public function availablePeriods(): void {
        check_ajax_referer('bvd_crm_front', 'nonce');
        $type = sanitize_key($_GET['type'] ?? 'month');
        global $wpdb;
        if ('quarter' === $type) {
            $sql = "SELECT DISTINCT CONCAT(YEAR(work_date), '-Q', CEIL(MONTH(work_date)/3)) AS period
                    FROM {$wpdb->prefix}bvd_timesheets
                    ORDER BY period DESC LIMIT 12";
        } else {
            $sql = "SELECT DISTINCT DATE_FORMAT(work_date, '%Y-%m') AS period
                    FROM {$wpdb->prefix}bvd_timesheets
                    ORDER BY period DESC LIMIT 15";
        }
        $periods = $wpdb->get_col($sql);
        wp_send_json_success($periods);
    }

    /* ─────────────────────────────────────────────────────────────
       Helper – WHERE filter for period
       ──────────────────────────────────────────────────────────── */
    private function periodWhere(string $type='month', ?string $val=null): array
    {
        if ($type === 'quarter') {
            if (!preg_match('/^(\d{4})-Q([1-4])$/', $val ?? '', $m)) {
                $val = Date::quarterFromDate(current_time('mysql'));
                preg_match('/^(\d{4})-Q([1-4])$/', $val, $m);
            }
            [$y,$q] = [(int)$m[1], (int)$m[2]];
            $months = range(($q-1)*3+1, $q*3);
            return [
                "AND YEAR(work_date)=%d AND MONTH(work_date) IN("
                    .implode(',',array_fill(0,3,'%d')).')',
                array_merge([$y], $months)
            ];
        }

        // default = month
        if (!preg_match('/^(\d{4})-(\d{2})$/', $val ?? '', $m)) {
            $val = Date::monthFromDate(current_time('mysql'));
            preg_match('/^(\d{4})-(\d{2})$/', $val, $m);
        }
        return [
            "AND YEAR(work_date)=%d AND MONTH(work_date)=%d",
            [(int)$m[1], (int)$m[2]]
        ];
    }
}
