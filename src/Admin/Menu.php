<?php
/**
 * Register admin menus.
 *
 * @package BVD\CRM\Admin
 */

declare(strict_types=1);

namespace BVD\CRM\Admin;

final class Menu
{
    public function register(): void
    {
        add_action(
            'admin_menu',
            function () {
                $cap = 'manage_options';

                add_menu_page(
                    __('BVD CRM', 'bvd-crm'),
                    'BVD CRM',
                    $cap,
                    'bvd-crm',
                    [$this, 'dashboard'],
                    'dashicons-clipboard'
                );

                add_submenu_page('bvd-crm', 'Import CSV', 'Import CSV', $cap,
                    'bvd-crm-import',  [Pages\Import::class,  'render']);

                add_submenu_page('bvd-crm', 'Clients',    'Clients',    $cap,
                    'bvd-crm-clients', [Pages\Clients::class, 'render']);

                add_submenu_page('bvd-crm', 'Employees',  'Employees',  $cap,
                    'bvd-crm-employees', [Pages\Employees::class, 'render']);

                add_submenu_page('bvd-crm', 'Jobs',       'Jobs',       $cap,
                    'bvd-crm-jobs',    [Pages\Jobs::class,    'render']);

                add_submenu_page('bvd-crm', 'Tools',      'Tools',      $cap,
                    'bvd-crm-tools',   [Pages\Tools::class,   'render']);
            }
        );
    }

    public function dashboard(): void
    {
        echo '<div class="wrap"><h1>BVD CRM</h1><p>Select a submenu.</p></div>';
    }
}
