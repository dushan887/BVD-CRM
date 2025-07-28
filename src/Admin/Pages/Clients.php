<?php

declare(strict_types=1);

namespace BVD\CRM\Admin\Pages;

use BVD\CRM\Models\Client;

final class Clients
{
    public static function render(): void
    {
        global $wpdb;
        Client::boot($wpdb->prefix);
        $clients = Client::all();
        ?>
        <div class="wrap">
            <h1>Clients</h1>

            <form id="bvd-add-client" class="card card-body" style="max-width:720px;margin-bottom:1rem;">
                <h2 style="margin:0 0 .5rem;">Add Client</h2>
                <p>
                    <input type="text" name="name" placeholder="Name" required class="regular-text">
                    &nbsp; Monthly&nbsp;<input type="number" step="0.01" name="monthly_limit" value="1" style="width:90px;">
                    &nbsp; Quarterly&nbsp;<input type="number" step="0.01" name="quarterly_limit" value="3" style="width:90px;">
                </p>
                <p><textarea name="notes" rows="2" placeholder="Notes" style="width:100%;"></textarea></p>
                <p><button class="button button-primary">Add</button></p>
            </form>

            <table class="widefat striped">
                <thead><tr>
                    <th>Name</th><th>Monthly limit</th><th>Quarterly limit</th><th>Notes</th>
                </tr></thead>
                <tbody id="bvd-clients-tbody">
                <?php foreach ($clients as $c) : ?>
                    <tr data-id="<?= esc_attr($c['id']) ?>">
                        <td><?= esc_html($c['name']) ?></td>
                        <td class="bvd-editable" data-field="monthly_limit" contenteditable><?= esc_html($c['monthly_limit']) ?></td>
                        <td class="bvd-editable" data-field="quarterly_limit" contenteditable><?= esc_html($c['quarterly_limit']) ?></td>
                        <td class="bvd-editable" data-field="notes" contenteditable><?= esc_html($c['notes'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <p class="description">Click into a cell to edit &amp; simply tab/blur to save.</p>
        </div>
        <?php
    }
}