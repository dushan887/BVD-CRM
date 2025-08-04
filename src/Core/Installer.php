<?php
/**
 * Sets up / upgrades all DB tables for the BVD‑CRM plugin.
 *
 * @package BVD\CRM\Core
 */

declare(strict_types=1);

namespace BVD\CRM\Core;

use wpdb;

final class Installer {

	/* ===================================================================== *
	 *  ACTIVATE – fresh install                                             *
	 * ===================================================================== */
	public static function activate(): void {

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		$tables = [

			/* ------------------ clients ------------------ */
			"{$wpdb->prefix}bvd_clients"         => "
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(190) NOT NULL,
                monthly_limit   DECIMAL(6,2) NOT NULL DEFAULT 1.00,
                quarterly_limit DECIMAL(6,2) NOT NULL DEFAULT 3.00,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY name (name)
            ",

			/* ------------------ client aliases (NEW) ----- */
			"{$wpdb->prefix}bvd_client_aliases"  => "
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                client_id BIGINT(20) UNSIGNED NOT NULL,
                alias VARCHAR(190) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY alias (alias),        /* fast lookup */
                KEY client_id (client_id)        /* reverse FK */
            ",

			/* ------------------ employees ---------------- */
			"{$wpdb->prefix}bvd_employees"       => "
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(190) NOT NULL,
                slug VARCHAR(190) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug)
            ",

			/* ------------------ jobs --------------------- */
			"{$wpdb->prefix}bvd_jobs"            => "
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                job_code VARCHAR(50)  DEFAULT NULL,
                title    VARCHAR(255) NOT NULL,
                description    TEXT NULL,
                classification VARCHAR(100) DEFAULT NULL,
                estimate DECIMAL(6,2) DEFAULT NULL,
                notes    TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY job_code (job_code)
            ",

			/* ------------------ timesheets --------------- */
			"{$wpdb->prefix}bvd_timesheets"      => "
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                client_id   BIGINT(20) UNSIGNED NOT NULL,
                employee_id BIGINT(20) UNSIGNED NOT NULL,
                job_id      BIGINT(20) UNSIGNED NOT NULL,
                work_date   DATE NOT NULL,
                hours DECIMAL(6,2) NOT NULL,
                maintenance TINYINT(1) NOT NULL DEFAULT 0,
                internal    TINYINT(1) NOT NULL DEFAULT 0,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY client_id (client_id),
                KEY job_id    (job_id),
                KEY work_date (work_date)
            ",
		];

		/* -- create / update all tables ---------------- */
		foreach ( $tables as $tbl => $schema ) {
			dbDelta( "CREATE TABLE $tbl ($schema) $charset;" );
		}

		/* -- extra composite index for dashboards ------ */
		$wpdb->query(
			"CREATE INDEX work_summary
			   ON {$wpdb->prefix}bvd_timesheets (client_id, work_date)"
		); // phpcs:ignore WordPress.DB

		/* -- seed default employees -------------------- */
		$defaults = [ 'Dusan', 'Marko', 'Angus', 'Papaya', 'Kasia', 'Other' ];
		foreach ( $defaults as $name ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$wpdb->prefix}bvd_employees (name, slug)
                     VALUES ( %s, %s )",
					$name,
					sanitize_title( $name )
				)
			); // phpcs:ignore WordPress.DB
		}
	}

	/* ===================================================================== *
	 *  MAYBE‑UPGRADE – runs on every load                                   *
	 * ===================================================================== */
	public static function maybeUpgrade(): void {

		global $wpdb;

		/* -------- 1. ensure “notes” column on clients -------- */
		$tblClients = "{$wpdb->prefix}bvd_clients";
		$notesCol   = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW COLUMNS FROM $tblClients LIKE %s",
				'notes'
			)
		);
		if ( ! $notesCol ) {
			$wpdb->query(
				"ALTER TABLE $tblClients ADD COLUMN notes TEXT NULL AFTER quarterly_limit"
			); // phpcs:ignore WordPress.DB
		}

		/* -------- 2. ensure alias table exists --------------- */
		$tblAlias = "{$wpdb->prefix}bvd_client_aliases";
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $tblAlias ) ) !== $tblAlias ) {

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			dbDelta(
				"CREATE TABLE $tblAlias (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    client_id BIGINT(20) UNSIGNED NOT NULL,
                    alias VARCHAR(190) NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY alias (alias),
                    KEY client_id (client_id)
                ) {$wpdb->get_charset_collate()};"
			);
		}
	}
}
