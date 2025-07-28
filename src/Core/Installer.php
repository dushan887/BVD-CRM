<?php

declare(strict_types=1);

namespace BVD\CRM\Core;

use wpdb;

final class Installer
{
    public static function activate(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        $tables = [
            "{$wpdb->prefix}bvd_clients" => "
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(190) NOT NULL,
                monthly_limit DECIMAL(6,2) NOT NULL DEFAULT 1.00,
                quarterly_limit DECIMAL(6,2) NOT NULL DEFAULT 3.00,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY name (name)
            ",
            "{$wpdb->prefix}bvd_employees" => "
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(190) NOT NULL,
                slug VARCHAR(190) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug)
            ",
            "{$wpdb->prefix}bvd_jobs" => "
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                job_code VARCHAR(50) DEFAULT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                classification VARCHAR(100) DEFAULT NULL,
                estimate DECIMAL(6,2) DEFAULT NULL,
                notes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY job_code (job_code)
            ",
            "{$wpdb->prefix}bvd_timesheets" => "
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                client_id BIGINT(20) UNSIGNED NOT NULL,
                employee_id BIGINT(20) UNSIGNED NOT NULL,
                job_id BIGINT(20) UNSIGNED NOT NULL,
                work_date DATE NOT NULL,
                hours DECIMAL(6,2) NOT NULL,
                maintenance TINYINT(1) NOT NULL DEFAULT 0,
                internal TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY client_id (client_id),
                KEY job_id (job_id),
                KEY work_date (work_date)
            "
        ];

        foreach ($tables as $table => $schema) {
            dbDelta("CREATE TABLE $table ( $schema ) $charset;");
        }

        $wpdb->query("CREATE INDEX work_summary ON {$wpdb->prefix}bvd_timesheets (client_id, work_date)");

        // Seed default employees if empty.
        $defaults = ['Dusan', 'Marko', 'Angus', 'Papaya', 'Kasia', 'Other'];
        foreach ($defaults as $name) {
            $slug = sanitize_title($name);
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT IGNORE INTO {$wpdb->prefix}bvd_employees (name, slug) VALUES (%s, %s)",
                    $name,
                    $slug
                )
            );
        }
    }

  
    public static function maybeUpgrade(): void
    {
        global $wpdb;
        $table = "{$wpdb->prefix}bvd_clients";
        $col   = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW COLUMNS FROM $table LIKE %s",
                'notes'
            )
        );
        if (!$col) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN notes TEXT NULL AFTER quarterly_limit;");
        }
    }

}
