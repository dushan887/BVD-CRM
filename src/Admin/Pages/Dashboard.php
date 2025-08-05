<?php
declare(strict_types=1);
namespace BVD\CRM\Admin\Pages;
use BVD\CRM\Models\Timesheet;
final class Dashboard {
    public static function render(): void {
        global $wpdb;
        Timesheet::boot($wpdb->prefix);
        // Enqueue Chart.js
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1', [], '4.4.1', true);
        // Aggregate data for charts
        $billable_vs_non = $wpdb->get_row("
            SELECT SUM(CASE WHEN maintenance=0 AND internal=0 THEN hours ELSE 0 END) AS billable,
                   SUM(CASE WHEN maintenance=1 OR internal=1 THEN hours ELSE 0 END) AS non_billable
            FROM {$wpdb->prefix}bvd_timesheets
        ");
        $top_clients = $wpdb->get_results("
            SELECT c.name, SUM(t.hours) AS total_hours
            FROM {$wpdb->prefix}bvd_timesheets t
            JOIN {$wpdb->prefix}bvd_clients c ON c.id = t.client_id
            GROUP BY c.id ORDER BY total_hours DESC LIMIT 10
        ");
        ?>
        <div class="wrap">
            <h1>BVD CRM Dashboard</h1>
            <div style="display: flex; gap: 2rem;">
                <div style="width: 50%;">
                    <h2>Billable vs Non-Billable</h2>
                    <canvas id="billablePie" width="400" height="400"></canvas>
                </div>
                <div style="width: 50%;">
                    <h2>Top 10 Clients by Hours</h2>
                    <canvas id="topClientsBar" width="400" height="400"></canvas>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const pieCtx = document.getElementById('billablePie').getContext('2d');
                    new Chart(pieCtx, {
                        type: 'pie',
                        data: {
                            labels: ['Billable', 'Non-Billable'],
                            datasets: [{ data: [<?php echo $billable_vs_non->billable; ?>, <?php echo $billable_vs_non->non_billable; ?>], backgroundColor: ['#36a2eb', '#ff6384'] }]
                        }
                    });
                    const barCtx = document.getElementById('topClientsBar').getContext('2d');
                    new Chart(barCtx, {
                        type: 'bar',
                        data: {
                            labels: [<?php echo implode(',', array_map(fn($c) => "'".esc_js($c->name)."'", $top_clients)); ?>],
                            datasets: [{ label: 'Total Hours', data: [<?php echo implode(',', wp_list_pluck($top_clients, 'total_hours')); ?>], backgroundColor: '#ffce56' }]
                        },
                        options: { scales: { y: { beginAtZero: true } } }
                    });
                });
            </script>
        </div>
        <?php
    }
}