<?php

declare(strict_types=1);

namespace BVD\CRM\Admin\Pages;

use BVD\CRM\Models\Job;

final class Jobs
{
    public static function render(): void
    {
        global $wpdb;
        Job::boot($wpdb->prefix);
        $jobs = Job::all();
        ?>
        <div class="wrap">
            <h1>Jobs</h1>
            <table class="widefat striped">
                <thead><tr><th>Code</th><th>Title</th><th>Classification</th><th>Estimate</th></tr></thead>
                <tbody>
                <?php foreach ($jobs as $j) : ?>
                    <tr>
                        <td><?= esc_html($j['job_code']) ?></td>
                        <td><?= esc_html($j['title']) ?></td>
                        <td><?= esc_html($j['classification']) ?></td>
                        <td><?= esc_html($j['estimate']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
