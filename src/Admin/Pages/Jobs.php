<?php
/**
 * Jobs admin screen
 *
 * @package BVD\CRM\Admin\Pages
 */

declare(strict_types=1);

namespace BVD\CRM\Admin\Pages;

use BVD\CRM\Models\Job;

final class Jobs
{
    public static function render(): void
    {
        global $wpdb;
        Job::boot($wpdb->prefix);
        $jobs = Job::all(); ?>

        <div class="wrap">
            <h1>Jobs</h1>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Code</th><th>Title</th>
                        <th>Classification</th><th>Estimate (h)</th>
                    </tr>
                </thead>
                <tbody id="bvd-jobs-tbody">
                <?php foreach ($jobs as $j) : ?>
                    <tr data-id="<?= esc_attr($j['id']) ?>">
                        <td><?= esc_html($j['job_code']) ?: '—' ?></td>
                        <td><?= esc_html($j['title']) ?></td>
                        <td class="bvd-editable" data-field="classification"
                            contenteditable><?= esc_html($j['classification'] ?: '-') ?></td>
                        <td class="bvd-editable" data-field="estimate"
                            contenteditable><?= esc_html($j['estimate']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <p class="description">
                Change *Classification* or *Estimate* directly in‑place.
            </p>
        </div><?php
    }
}
