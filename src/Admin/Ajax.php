<?php
/**
 * Admin‑side AJAX endpoints.
 *
 * All calls must pass the security nonce generated with
 * `wp_create_nonce( 'bvd_crm_admin' )`.
 * We accept it in the modern field name `nonce`, but fall back to the
 * default WordPress `_wpnonce` so older links keep working.
 *
 * @package BVD\CRM\Admin
 */

declare(strict_types=1);

namespace BVD\CRM\Admin;

use BVD\CRM\CSV\Importer;

final class Ajax {

	/* --------------------------------------------------------------------- *
	 *  Boot                                                                 *
	 * --------------------------------------------------------------------- */
	public function register(): void {

		/* ---------- import ----------- */
		add_action( 'wp_ajax_bvd_crm_import_start',  [ $this, 'start' ] );
		add_action( 'wp_ajax_bvd_crm_import_chunk',  [ $this, 'chunk' ] );

	   /* ---------- clients ---------- */
	   add_action( 'wp_ajax_bvd_crm_client_update', [ $this, 'clientUpdate' ] );
	   add_action( 'wp_ajax_bvd_crm_client_add',    [ $this, 'clientAdd' ] );
	   add_action( 'wp_ajax_bvd_crm_clients_merge', [ $this, 'clientsMerge' ] );
	   add_action( 'wp_ajax_bvd_crm_clients_delete',[ $this, 'clientsDelete' ] );

		/* ---------- employees -------- */
		add_action( 'wp_ajax_bvd_crm_employee_update', [ $this, 'employeeUpdate' ] );
		add_action( 'wp_ajax_bvd_crm_employee_add',    [ $this, 'employeeAdd' ] );

		/* ---------- jobs ------------- */
		add_action( 'wp_ajax_bvd_crm_job_update', [ $this, 'jobUpdate' ] );

		/* ---------- misc ------------- */
		add_action( 'wp_ajax_bvd_crm_export',         [ $this, 'export' ] );
		add_action( 'wp_ajax_bvd_crm_nuke',           [ $this, 'nuke' ] );
		add_action( 'wp_ajax_bvd_crm_refresh_nonce',  [ $this, 'refreshNonce' ] );
	}

	/* ==================================================================== *
	 *  CLIENTS                                                             *
	 * ==================================================================== */
	public function clientUpdate(): void {

		$this->verifyNonce();
		$this->verifyCap();

		$id    = (int) ( $_POST['id'] ?? 0 );
		$field = sanitize_key( $_POST['field'] ?? '' );
		$value = wp_unslash( $_POST['value'] ?? '' );

		if ( ! $id || ! in_array( $field, [ 'monthly_limit', 'quarterly_limit', 'notes' ], true ) ) {
			wp_send_json_error( 'Bad request', 400 );
		}

		if ( in_array( $field, [ 'monthly_limit', 'quarterly_limit' ], true ) ) {
			$value = round( (float) $value, 2 );
		}

		global $wpdb;
		$wpdb->update( "{$wpdb->prefix}bvd_clients", [ $field => $value ], [ 'id' => $id ] );
		wp_send_json_success();
	}

	public function clientAdd(): void {

		$this->verifyNonce();

		$name            = sanitize_text_field( $_POST['name']            ?? '' );
		$monthly_limit   = round( (float) ( $_POST['monthly_limit']   ?? 1 ), 2 );
		$quarterly_limit = round( (float) ( $_POST['quarterly_limit'] ?? 3 ), 2 );
		$notes           = wp_unslash( $_POST['notes'] ?? '' );

		if ( '' === $name ) {
			wp_send_json_error( 'Name required', 400 );
		}

		global $wpdb;
		$wpdb->insert(
			"{$wpdb->prefix}bvd_clients",
			compact( 'name', 'monthly_limit', 'quarterly_limit', 'notes' )
		);

		wp_send_json_success(
			[
				'row' => [
					'id'              => $wpdb->insert_id,
					'name'            => $name,
					'monthly_limit'   => $monthly_limit,
					'quarterly_limit' => $quarterly_limit,
					'notes'           => $notes,
				],
			]
		);
	}

	public function clientsMerge(): void {

		$this->verifyNonce();

		$target = (int) ($_POST['target'] ?? 0);
		$ids    = array_unique( array_map('intval', $_POST['ids'] ?? []) );

		// sanity …
		if ( $target === 0 || count($ids) < 2 || ! in_array($target, $ids, true) ) {
			wp_send_json_error( 'Bad request', 400 );
		}
		$victims = array_diff( $ids, [ $target ] );

		global $wpdb;

		/* --------------------------------------------------------
		* 1.  Save ALIASES – each victim’s *name* points to target
		* ------------------------------------------------------ */
		if ( $victims ) {
			$names = $wpdb->get_results(
				"SELECT id,name FROM {$wpdb->prefix}bvd_clients
				WHERE id IN (" . implode( ',', $victims ) . ")", OBJECT_K );

			foreach ( $names as $row ) {
				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$wpdb->prefix}bvd_client_aliases
						(client_id, alias) VALUES ( %d, %s )",
						$target,
						$row->name
					)
				);
				// also migrate any existing aliases of the victim
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}bvd_client_aliases
							SET client_id = %d
						WHERE client_id = %d",
						$target,
						$row->id
					)
				);
			}
		}

		/* --------------------------------------------------------
		* 2.  Move timesheets & purge redundant rows  (unchanged)
		* ------------------------------------------------------ */
		$in = implode( ',', array_fill( 0, count( $victims ), '%d' ) );

		if ( $victims ) {
			// point timesheets to target
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}bvd_timesheets
						SET client_id = %d
					WHERE client_id IN ($in)",
					array_merge( [ $target ], $victims )
				)
			);

			// delete client rows
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}bvd_clients
					WHERE id IN ($in)",
					$victims
				)
			);
		}

		wp_send_json_success();
	}


	/* ==================================================================== *
	 *  EMPLOYEES                                                           *
	 * ==================================================================== */
	public function employeeUpdate(): void {

		$this->verifyNonce();

		$id    = (int) ( $_POST['id'] ?? 0 );
		$field = sanitize_key( $_POST['field'] ?? '' );
		$value = sanitize_text_field( $_POST['value'] ?? '' );

		if ( ! $id || 'name' !== $field ) {
			wp_send_json_error( 'Bad request', 400 );
		}

		global $wpdb;
		$wpdb->update( "{$wpdb->prefix}bvd_employees", [ 'name' => $value ], [ 'id' => $id ] );
		wp_send_json_success();
	}

	public function employeeAdd(): void {

		$this->verifyNonce();

		$name = sanitize_text_field( $_POST['name'] ?? '' );
		if ( '' === $name ) {
			wp_send_json_error( 'Name required', 400 );
		}

		global $wpdb;
		$wpdb->insert(
			"{$wpdb->prefix}bvd_employees",
			[
				'name' => $name,
				'slug' => sanitize_title( $name ),
			]
		);

		wp_send_json_success(
			[
				'row' => [
					'id'   => $wpdb->insert_id,
					'name' => $name,
				],
			]
		);
	}

	/* ==================================================================== *
	 *  JOBS                                                                *
	 * ==================================================================== */
	public function jobUpdate(): void {

		$this->verifyNonce();

		$id    = (int) ( $_POST['id'] ?? 0 );
		$field = sanitize_key( $_POST['field'] ?? '' );
		$value = wp_unslash( $_POST['value'] ?? '' );

		if ( ! $id || ! in_array( $field, [ 'classification', 'estimate' ], true ) ) {
			wp_send_json_error( 'Bad request', 400 );
		}

		if ( 'estimate' === $field ) {
			$value = ( '' === $value ) ? null : round( (float) $value, 2 );
		}

		global $wpdb;
		$wpdb->update( "{$wpdb->prefix}bvd_jobs", [ $field => $value ], [ 'id' => $id ] );
		wp_send_json_success();
	}

	/* ==================================================================== *
	 *  IMPORT – chunked CSV upload                                         *
	 * ==================================================================== */
	public function start(): void {

		$this->verifyNonce();

		if ( empty( $_FILES['file']['tmp_name'] ) ) {
			wp_send_json_error( 'No file', 400 );
		}
		$upload = wp_upload_dir();
		$dest   = trailingslashit( $upload['basedir'] ) .
				  'bvd_tmp_' . time() . '.csv';

		if ( ! move_uploaded_file( $_FILES['file']['tmp_name'], $dest ) ) {
			wp_send_json_error( 'Move failed', 500 );
		}

		wp_send_json_success(
			[
				'path'    => $dest,
				'pointer' => 0,
				'filesize'=> filesize( $dest ),
				'rows'    => 0,          // nothing processed yet
			]
		);
	}

	public function chunk(): void {

		$this->verifyNonce();

		$path    = sanitize_text_field( $_POST['path']    ?? '' );
		$pointer = (int)             ( $_POST['pointer'] ?? 0 );

		if ( ! is_readable( $path ) ) {
			wp_send_json_error( 'Bad path', 400 );
		}

		try {
			$importer = new Importer( $path, $pointer );
			$result   = $importer->handle();              // ['next'=>?, 'rows'=>n]

			if ( null === $result['next'] ) {
				@unlink( $path );                         // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
				wp_send_json_success(
					[
						'done'    => true,
						'rows'    => $result['rows'],
						'pointer' => (int) $_POST['pointer'] + $result['bytes'],
					]
				);
			}

			wp_send_json_success(
				[
					'pointer' => $result['next'],
					'rows'    => $result['rows'],
					'bytes'   => $result['bytes'],
				]
			);

		} catch ( \Throwable $e ) {                       // @phpstan-ignore-line
			wp_send_json_error( $e->getMessage(), 500 );
		}
	}

	/* ==================================================================== *
	 *  EXPORT                                                              *
	 * ==================================================================== */
	public function export(): void {

		$this->verifyNonce();

		global $wpdb;
		$tables = [ 'clients', 'employees', 'jobs', 'timesheets' ];

		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename=bvd_crm_export_' . gmdate( 'Ymd_His' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );

		foreach ( $tables as $t ) {
			$full = $wpdb->prefix . 'bvd_' . $t;
			fputcsv( $out, [ "TABLE:$full" ] );                  // phpcs:ignore
			$rows = $wpdb->get_results( "SELECT * FROM $full", ARRAY_A );

			if ( $rows ) {
				fputcsv( $out, array_keys( $rows[0] ) );         // phpcs:ignore
				foreach ( $rows as $r ) {
					fputcsv( $out, $r );                          // phpcs:ignore
				}
			}
			fputcsv( $out, [] ); // blank line between tables     // phpcs:ignore
		}
		exit;
	}

	/* ==================================================================== *
	 *  NUKE – irreversible wipe                                            *
	 * ==================================================================== */
	public function nuke(): void {

		$this->verifyNonce();

		global $wpdb;
		foreach ( [ 'timesheets', 'jobs', 'employees', 'clients' ] as $tbl ) {
			$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bvd_$tbl" ); // phpcs:ignore
		}
		wp_send_json_success();
	}

	/* ==================================================================== *
	 *  UTILITIES                                                           *
	 * ==================================================================== */
	public function refreshNonce(): void {
		wp_send_json_success(
			[
				'nonce' => wp_create_nonce( 'bvd_crm_admin' ),
			]
		);
	}

	/**
	 * Unified nonce verification helper – accepts either `nonce` or `_wpnonce`.
	 */
	private function verifyNonce(): void {
		if ( isset( $_POST['nonce'] ) || isset( $_GET['nonce'] ) ) {
			check_admin_referer( 'bvd_crm_admin', 'nonce' );
		} else {
			check_admin_referer( 'bvd_crm_admin' ); // fallback
		}
	}
	/**
	 * Capability check helper.
	 */
	private function verifyCap( string $cap = 'manage_options' ): void {
		if ( ! current_user_can( $cap ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}
	}
}
