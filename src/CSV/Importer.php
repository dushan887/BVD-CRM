<?php
/**
 * Streaming CSV importer – handles 1 000‑row chunks, robust multi‑ID
 * parsing and equal hour splitting.
 *
 * @package BVD\CRM\CSV
 */

declare(strict_types=1);

namespace BVD\CRM\CSV;

use BVD\CRM\Models\Client;
use BVD\CRM\Models\Employee;
use BVD\CRM\Models\Job;
use BVD\CRM\Models\Timesheet;

final class Importer {

	private string $file;
	private int    $ptr;
	private array  $map   = [];
	private int    $year;
	private const  CHUNK  = 1_000;

	public function __construct( string $file, int $pointer = 0 ) {

		$this->file = $file;
		$this->ptr  = $pointer;

		/* –– infer work‑year from file‑name (YYYY) ––––––––––– */
		if ( preg_match( '/(20\d{2})/', basename( $file ), $m ) ) {
			$this->year = (int) $m[1];
		} else {
			$this->year = (int) current_time( 'Y' );
		}

		/* –– boot models –––––––––––––––––––––––––––––––––––– */
		global $wpdb;
		Client   ::boot( $wpdb->prefix );
		Employee ::boot( $wpdb->prefix );
		Job      ::boot( $wpdb->prefix );
		Timesheet::boot( $wpdb->prefix );
	}

	/**
	 * Process next chunk.
	 *
	 * @return array{next:int|null,rows:int,bytes:int}
	 */
	public function handle(): array {

		if ( ! is_readable( $this->file ) ) {
			throw new \RuntimeException( 'CSV not readable' );
		}
		$fh = fopen( $this->file, 'r' );
		if ( ! $fh ) {
			throw new \RuntimeException( 'Open failed' );
		}
		fseek( $fh, $this->ptr );

		$rows  = 0;
		$bytes = 0;
		$hadHd = ( $this->ptr !== 0 );

		while ( $rows < self::CHUNK && ( $row = fgetcsv( $fh, 0, ',' ) ) !== false ) {

			$bytes += strlen( implode( ',', $row ) ) + 1; // crude but OK

			if ( ! $hadHd ) {
				$this->captureHeader( $row );
				$hadHd = true;
				continue;
			}

			$this->mapAndSave( $row );
			$rows++;
		}

		$next = feof( $fh ) ? null : ftell( $fh );
		fclose( $fh );

		return [
			'next'  => $next,
			'rows'  => $rows,
			'bytes' => $bytes,
		];
	}

	/* ======================================================
	 *  INTERNAL helpers
	 * ==================================================== */

	private function captureHeader( array $hdr ): void {

		foreach ( $hdr as $i => $lab ) {
			if ( $lab !== '' ) {
				$this->map[ strtolower( trim( $lab ) ) ] = $i;
			}
		}
		/* fallback template for legacy CSVs (unchanged) */
		if ( ! isset( $this->map['client'] ) ) {
			$this->map = [
				'client'         => 1,
				'dusan'          => 4,
				'papaya'         => 5,
				'angus'          => 6,
				'marko'          => 7,
				'kasia'          => 8,
				'other'          => 9,
				'description'    => 12,
				'date'           => 13,
				'maintenance'    => 14,
				'internal'       => 15,
				'classification' => 18,
				'estimate'       => 19,
				'notes'          => 20,
			];
		}
	}

	/** Import one CSV data row. */
	private function mapAndSave( array $r ): void {

		$clientName = trim( $r[ $this->idx( 'client' ) ] ?? '' );
		if ( $clientName === '' ) {
			return;
		}
		$clientId = Client::upsertByName( $clientName );

		/* ---- employee hours -------------------------------- */
		$empOrder = [ 'Dusan', 'Papaya', 'Angus', 'Marko', 'Kasia', 'Other' ];
		$hrs      = array_map(
			fn( string $e ) => $r[ $this->idx( strtolower( $e ) ) ] ?? '',
			$empOrder
		);

		/* ---- description & job‑codes ----------------------- */
		$desc  = $r[ $this->idx( 'description' ) ] ?? '';
		$codes = $this->extractCodes( $desc );          // NEW logic
		$nCode = \count( $codes );

		$class = $r[ $this->idx( 'classification' ) ] ?? null;
		$est   = trim( $r[ $this->idx( 'estimate' ) ] ?? '' );
		$est   = $est !== '' ? (float) $est : null;
		$notes = $r[ $this->idx( 'notes' ) ] ?? null;

		$date  = self::normaliseDate(
			$r[ $this->idx( 'date' ) ] ?? '',
			$this->year
		);
		$maint = trim( $r[ $this->idx( 'maintenance' ) ] ?? '' ) !== '';
		$intern= trim( $r[ $this->idx( 'internal' ) ]    ?? '' ) !== '';

		/* ---- DB writes ------------------------------------- */
		foreach ( $codes as $code ) {

			$jobId = Job::upsert(
				$code,
				trim( str_replace( $code, '', $desc ) ) ?: $code ?: $desc,
				[
					'description'    => $desc,
					'classification' => $class ?: null,
					'estimate'       => $est,
					'notes'          => $notes ?: null,
				]
			);

			foreach ( $hrs as $i => $hRaw ) {
				$h = (float) $hRaw;
				if ( $h <= 0 ) { continue; }

				$empId = Employee::upsert( $empOrder[ $i ] );
				$split = $h / $nCode;                // equal share

				Timesheet::upsert(
					[
						'client_id'   => $clientId,
						'employee_id' => $empId,
						'job_id'      => $jobId,
						'work_date'   => $date,
						'hours'       => $split,
						'maintenance' => $maint ? 1 : 0,
						'internal'    => $intern ? 1 : 0,
					]
				);
			}
		}
	}

	/* ---------- code splitter ------------------------------------------ */
	private function extractCodes( string $desc ): array {

		$tokens = preg_split( '/[,\s\/\.]+/', $desc, -1, PREG_SPLIT_NO_EMPTY );
		$codes  = [];
		$prefix = 'MKT-';

		foreach ( $tokens as $tok ) {

			$tok = strtoupper( trim( $tok ) );

			if ( preg_match( '/^(MKT-)?(\d{3,})$/', $tok, $m ) ) {
				if ( $m[1] !== '' ) {               // explicit prefix
					$prefix = $m[1];                // remember for later
				}
				$codes[] = ( $m[1] ?: $prefix ) . $m[2];
			}
		}
		return $codes ?: [ '' ];
	}

	private static function normaliseDate( string $dm, int $y ): string {
		[ $d, $m ] = array_pad( explode( '/', $dm ), 2, 1 );
		return sprintf( '%04d-%02d-%02d', $y, (int) $m, (int) $d );
	}

	private function idx( string $lab ): int {
		return $this->map[ strtolower( $lab ) ] ?? -1;
	}
}
