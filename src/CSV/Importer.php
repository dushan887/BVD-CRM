<?php

declare(strict_types=1);

namespace BVD\CRM\CSV;

use BVD\CRM\Models\Client;
use BVD\CRM\Models\Employee;
use BVD\CRM\Models\Job;
use BVD\CRM\Models\Timesheet;

final class Importer
{
    private string $filepath;
    private int $pointer;
    private const CHUNK = 1000;

    public function __construct(string $filepath, int $pointer = 0)
    {
        $this->filepath = $filepath;
        $this->pointer  = $pointer;

        // Boot models with prefix.
        global $wpdb;
        Client::boot($wpdb->prefix);
        Employee::boot($wpdb->prefix);
        Job::boot($wpdb->prefix);
        Timesheet::boot($wpdb->prefix);
    }

    /**
     * Process next chunk, return new file pointer or null when finished.
     */
    public function handle(): ?int
    {
        if (!is_readable($this->filepath)) {
            throw new \RuntimeException('CSV not readable');
        }

        $fp = fopen($this->filepath, 'r');
        if (!$fp) {
            throw new \RuntimeException('Failed to open CSV');
        }

        // Move to last position.
        fseek($fp, $this->pointer);

        $rows = 0;
        $isHeader = ($this->pointer === 0 && $rows === 0);
        while ($rows < self::CHUNK && ($data = fgetcsv($fp, 0, ',')) !== false) {
            if ($isHeader) { // skip only once
                $rows++;
                $isHeader = false;
                continue;
            }

            $this->mapAndSave($data);
            $rows++;
        }

        $newPointer = ftell($fp);
        fclose($fp);

        // End of file?
        if (feof($fp) || $data === false) {
            return null;
        }

        return $newPointer;
    }

    /**
     * Map one CSV row into DB records.
     *
     * Cols: B -> client, E‑J employees (6 numbers), M description, N date (d/m),
     *       O maintenance, P internal, S classification, T estimate, U notes
     *
     * @param string[] $row
     */
    private function mapAndSave(array $row): void
    {
        // always work with at least 21 cells so numeric indexes are safe
        $row = array_pad($row, 21, '');

        /* -----------------------------------------------------------------
         * 1)  If description column (12) looks like a date (e.g. 6/Apr, 13/6)
         *     we assume one hour‑cell was missing → re‑insert an empty cell
         *     before column‑index 4 (the first employee).
         * ----------------------------------------------------------------*/
        $looksLikeDate = static function (string $v): bool {
            return (bool) preg_match(
            '/^\d{1,2}\s*[\/\-]\s*(\d{1,2}|[A-Za-z]{3})$/',
            trim($v)
            );
        };

        if ($looksLikeDate($row[12])) {
            array_splice($row, 4, 0, ['']);   // insert a blank "hours" cell
            $row = array_slice($row, 0, 21);  // keep array length constant
        }

        // ------------------------------------------------------------
        // from here on the rest of the original method stays unchanged

        // Safe column accessor
        $col = static function (array $row, int $idx): string {
            return $row[$idx] ?? '';
        };

        $clientName = trim($col($row, 1));
        if ('' === $clientName) {
            return;
        }

        $clientId = Client::upsertByName($clientName);

        // Employees hours: E (4) … J (9)
        $employeeCols = ['Dusan', 'Papaya', 'Angus', 'Marko', 'Kasia', 'Other'];
        $hoursPerEmp  = array_map(fn($i) => $col($row, $i), range(4, 9));

        // Job parsing from col M (12)
        $desc = $col($row, 12);

        // 1) grab full "MKT-1234" tokens
        preg_match_all('/MKT-\d{3,}/i', $desc, $matches);
        $codes = $matches[0];

        // 2) handle ",1234" trailing numbers (inherit prefix from last real code)
        if ($codes && preg_match_all('/,\s*(\d{3,})/', $desc, $tail)) {
            $base = explode('-', end($codes))[0] . '-';
            foreach ($tail[1] as $num) { $codes[] = $base . $num; }
        }

        // If nothing matched we still create one pseudo‑code = ''
        $codes = $codes ?: [''];

        // Date
        $ymd = self::normalizeDate($col($row, 13));

        $maintenance = trim($col($row, 14)) !== '';
        $internal    = trim($col($row, 15)) !== '';

        /* ----------------------------------------------------- */
        foreach ($codes as $jobCode) {
            $jobId = Job::upsert(
                strtoupper($jobCode),
                trim(str_replace($jobCode, '', $desc)) ?: $jobCode ?: $desc,
                [
                    'description'   => $desc,
                    'classification'=> $col($row, 18) ?: null,
                    'estimate'      => ($v = trim($col($row, 19))) === '' ? null : (float) $v,
                    'notes'         => $col($row, 20) ?: null
                ]
            );

            foreach ($hoursPerEmp as $idx => $hours) {
                $h = (float) $hours;
                if ($h <= 0) {
                    continue;
                }

                $empName = $employeeCols[$idx];
                $empId   = Employee::upsert($empName);

                Timesheet::upsert([
                    'client_id'  => $clientId,
                    'employee_id'=> $empId,
                    'job_id'     => $jobId,
                    'work_date'  => $ymd,
                    'hours'      => $h,
                    'maintenance'=> $maintenance ? 1 : 0,
                    'internal'   => $internal ? 1 : 0,
                ]);
            }
        }
    }

    /**
     * Convert d/m (assume current year) → Y‑m‑d.
     */
    private static function normalizeDate(string $dm): string
    {
        [$d, $m] = array_pad(explode('/', $dm), 2, 1);
        $year    = (int) current_time('Y');
        $ymd     = sprintf('%04d-%02d-%02d', $year, (int) $m, (int) $d);

        return $ymd;
    }
}
