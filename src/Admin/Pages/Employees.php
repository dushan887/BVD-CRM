<?php
/**
 * Employees admin screen
 *
 * @package BVD\CRM\Admin\Pages
 */

declare(strict_types=1);

namespace BVD\CRM\Admin\Pages;

use BVD\CRM\Models\Employee;

final class Employees
{
    public static function render(): void
    {
        global $wpdb;
        Employee::boot($wpdb->prefix);
        $emps = Employee::all(); ?>

        <div class="wrap">
            <h1>Employees</h1>

            <form id="bvd-add-employee" class="card card-body"
                  style="max-width:420px;margin-bottom:1rem;">
                <h2 style="margin:0 0 .5rem;">Add Employee</h2>
                <p>
                    <input type="text" name="name" placeholder="Name"
                           required class="regular-text">
                    <button class="button button-primary">Add</button>
                </p>
            </form>

            <table class="widefat striped">
                <thead><tr><th>Name</th></tr></thead>
                <tbody id="bvd-employees-tbody">
                <?php foreach ($emps as $e) : ?>
                    <tr data-id="<?= esc_attr($e['id']) ?>">
                        <td class="bvd-editable" data-field="name"
                            contenteditable><?= esc_html($e['name']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <p class="description">
                Click the name to edit. Blur / tab to save.
            </p>
        </div><?php
    }
}
