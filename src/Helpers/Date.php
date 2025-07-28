<?php

declare(strict_types=1);

namespace BVD\CRM\Helpers;

final class Date
{
    public static function quarterFromDate(string $ymd): string
    {
        $month = (int) date('n', strtotime($ymd));
        $year  = (int) date('Y', strtotime($ymd));
        $q     = (int) ceil($month / 3);

        return sprintf('%d‑Q%d', $year, $q); // e.g. 2025‑Q3
    }

    public static function monthFromDate(string $ymd): string
    {
        return date('Y‑m', strtotime($ymd)); // e.g. 2025‑07
    }
}
