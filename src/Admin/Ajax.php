<?php

declare(strict_types=1);

namespace BVD\CRM\Admin;

use BVD\CRM\CSV\Importer;

final class Ajax
{
    public function register(): void
    {
        add_action('wp_ajax_bvd_crm_import_start',  [$this, 'start']);
        add_action('wp_ajax_bvd_crm_import_chunk',  [$this, 'chunk']);

        add_action('wp_ajax_bvd_crm_client_update', [$this, 'clientUpdate']);
        add_action('wp_ajax_bvd_crm_client_add',    [$this, 'clientAdd']);

        add_action('wp_ajax_bvd_crm_export', [$this,'export']);
        add_action('wp_ajax_bvd_crm_nuke',   [$this,'nuke']);
    }

    /* ------------------------------------------------------------------ */
    /*  Update one field (inline edit)                                    */
    /* ------------------------------------------------------------------ */
    public function clientUpdate(): void
    {
        check_admin_referer('bvd_crm_admin', 'nonce');

        $id    = (int) ($_POST['id'] ?? 0);
        $field = sanitize_key($_POST['field'] ?? '');
        $value = wp_unslash($_POST['value'] ?? '');

        if (!$id || !in_array($field, ['monthly_limit', 'quarterly_limit', 'notes'], true)) {
            wp_send_json_error('Bad request', 400);
        }

        global $wpdb;
        $table = "{$wpdb->prefix}bvd_clients";

        // Cast numbers
        if (in_array($field, ['monthly_limit', 'quarterly_limit'], true)) {
            $value = round((float) $value, 2);
        }

        $wpdb->update($table, [$field => $value], ['id' => $id]);

        wp_send_json_success();
    }

    /* ------------------------------------------------------------------ */
    /*  Add new client                                                    */
    /* ------------------------------------------------------------------ */
    public function clientAdd(): void
    {
        check_admin_referer('bvd_crm_admin', 'nonce');

        $name            = sanitize_text_field($_POST['name'] ?? '');
        $monthly_limit   = round((float) ($_POST['monthly_limit'] ?? 1), 2);
        $quarterly_limit = round((float) ($_POST['quarterly_limit'] ?? 3), 2);
        $notes           = wp_unslash($_POST['notes'] ?? '');

        if ('' === $name) {
            wp_send_json_error('Name required', 400);
        }

        global $wpdb;
        $table = "{$wpdb->prefix}bvd_clients";

        $wpdb->insert($table, compact('name', 'monthly_limit', 'quarterly_limit', 'notes'));

        wp_send_json_success(['row' => [
            'id'              => $wpdb->insert_id,
            'name'            => $name,
            'monthly_limit'   => $monthly_limit,
            'quarterly_limit' => $quarterly_limit,
            'notes'           => $notes,
        ]]);
    }

    public function start(): void
    {
        check_admin_referer('bvd_crm_admin', 'nonce');

        if (empty($_FILES['file']['tmp_name'])) {
            wp_send_json_error('No file', 400);
        }

        // Move to uploads
        $uploaddir = wp_upload_dir();
        $dest = trailingslashit($uploaddir['basedir']) . 'bvd_tmp_' . time() . '.csv';
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
            wp_send_json_error('Move failed', 500);
        }

        wp_send_json_success(['token' => wp_hash($dest), 'path' => $dest, 'pointer' => 0]);
    }

    public function chunk(): void
    {
        check_admin_referer('bvd_crm_admin', 'nonce');

        $path    = sanitize_text_field($_POST['path'] ?? '');
        $pointer = (int) ($_POST['pointer'] ?? 0);

        if (!is_readable($path)) {
            wp_send_json_error('Bad path', 400);
        }

        try {
            $importer = new Importer($path, $pointer);
            $next     = $importer->handle();

            if ($next === null) {
                @unlink($path);
                wp_send_json_success(['done' => true]);
            }

            wp_send_json_success(['pointer' => $next]);
        } catch (\Throwable $e) {
            wp_send_json_error($e->getMessage(), 500);
        }
    }

    public function export():void{
        check_admin_referer('bvd_crm_admin');
        global $wpdb;
        $tables=['clients','employees','jobs','timesheets'];
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=bvd_crm_export_'.date('Ymd_His').'.csv');
        $out=fopen('php://output','w');
        foreach($tables as $t){
            $full=$wpdb->prefix."bvd_$t";
            fputcsv($out,["TABLE:$full"]);
            $rows=$wpdb->get_results("SELECT * FROM $full",ARRAY_A);
            if($rows){ fputcsv($out,array_keys($rows[0]));
                foreach($rows as $r){ fputcsv($out,$r); }
            }
            fputcsv($out,[]); // blank line between tables
        }
        exit;
    }

    public function nuke():void{
        check_admin_referer('bvd_crm_admin');
        global $wpdb;
        foreach(['timesheets','jobs','employees','clients'] as $t){
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}bvd_$t");
        }
        wp_send_json_success();
    }
}
