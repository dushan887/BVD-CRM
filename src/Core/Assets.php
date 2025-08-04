<?php

declare(strict_types=1);

namespace BVD\CRM\Core;

final class Assets
{
    public static function register(): void
    {
        // Admin assets (always).
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin']);

        // Front‑end assets (only when shortcode found).
        add_action('wp_enqueue_scripts', [self::class, 'maybe_enqueue_front']);
    }

    public static function enqueue_admin(string $hook): void
    {
        $suffix = '';

        wp_register_style(
            'bvd-crm-admin',
            BVD_CRM_URL . 'assets/css/admin.css',
            [],
            BVD_CRM_VERSION
        );

        wp_register_script(
            'bvd-crm-admin',
            BVD_CRM_URL . "assets/js/admin-import{$suffix}.js",
            ['jquery'],
            BVD_CRM_VERSION,
            true
        );

        // Localize nonce & ajax URL.
        wp_localize_script('bvd-crm-admin', 'BVDCRMAdmin', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bvd_crm_admin'),
        ]);

        wp_enqueue_style('bvd-crm-admin');
        wp_enqueue_script('bvd-crm-admin');

        // Enqueue the new JS only on Clients page
        if (isset($_GET['page']) && $_GET['page'] === 'bvd-crm-clients') {
            wp_enqueue_script(
                'bvd-crm-clients',
                BVD_CRM_URL . 'assets/js/admin-clients.js',
                ['jquery'],
                BVD_CRM_VERSION,
                true
            );
            wp_localize_script('bvd-crm-clients', 'BVDCRMAdmin', [
                'ajax'  => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bvd_crm_admin'),
            ]);
        }
        elseif (isset($_GET['page']) && $_GET['page'] === 'bvd-crm-employees') {
            wp_enqueue_script(
                'bvd-crm-employees',
                BVD_CRM_URL . 'assets/js/admin-employees.js',
                ['jquery'],
                BVD_CRM_VERSION,
                true
            );
            wp_localize_script('bvd-crm-employees', 'BVDCRMAdmin', [
                'ajax'  => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bvd_crm_admin'),
            ]);
        }
        elseif (isset($_GET['page']) && $_GET['page'] === 'bvd-crm-jobs') {
            wp_enqueue_script(
                'bvd-crm-jobs',
                BVD_CRM_URL . 'assets/js/admin-jobs.js',
                ['jquery'],
                BVD_CRM_VERSION,
                true
            );
            wp_localize_script('bvd-crm-jobs', 'BVDCRMAdmin', [
                'ajax'  => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bvd_crm_admin'),
            ]);
        }
        elseif (isset($_GET['page']) && $_GET['page']==='bvd-crm-tools'){
            wp_enqueue_script('bvd-crm-tools',BVD_CRM_URL.'assets/js/admin-tools.js',['jquery'],BVD_CRM_VERSION,true);
            wp_localize_script('bvd-crm-tools','BVDCRMAdmin',['ajax'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('bvd_crm_admin')]);
        }
    }

    public static function maybe_enqueue_front(): void
    {
        global $post;
        if (!($post && has_shortcode($post->post_content ?? '', 'bvd_client_summary'))) {
            return;
        }

        $suffix = '';

        // Bootstrap & DataTables via CDN.
        wp_enqueue_style(
            'bootstrap',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
            [],
            '5.3.3'
        );
        wp_enqueue_style(
            'datatables',
            'https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.10/css/dataTables.bootstrap5.min.css',
            ['bootstrap'],
            '1.13.10'
        );

        wp_enqueue_script(
            'bootstrap',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
            ['jquery'],
            '5.3.3',
            true
        );
        wp_enqueue_script(
            'datatables',
            'https://cdn.jsdelivr.net/npm/datatables.net@1.13.10/js/jquery.dataTables.min.js',
            ['jquery'],
            '1.13.10',
            true
        );
        wp_enqueue_script(
            'datatables-bs',
            'https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.10/js/dataTables.bootstrap5.min.js',
            ['datatables', 'bootstrap'],
            '1.13.10',
            true
        );

        // Plugin front assets.
        wp_register_style(
            'bvd-crm-front',
            BVD_CRM_URL . 'assets/css/frontend.css',
            ['bootstrap', 'datatables'],
            BVD_CRM_VERSION
        );
        wp_register_script(
            'bvd-crm-front',
            BVD_CRM_URL . "assets/js/frontend-summary{$suffix}.js",
            ['jquery', 'bootstrap', 'datatables-bs'],
            BVD_CRM_VERSION,
            true
        );

        wp_localize_script('bvd-crm-front', 'BVDCRM', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bvd_crm_front'),
            'isAdmin' => current_user_can('administrator'),
        ]);

        wp_enqueue_style('bvd-crm-front');
        wp_enqueue_script('bvd-crm-front');
    }
}
