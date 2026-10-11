<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * The two donuts of the page as rows: where the period's bookings came
 * from, and what status they are in now.
 */
class ReportBreakdownSheet implements FromArray, WithHeadings, WithStrictNullComparison, WithTitle
{
    public function __construct(private array $bySource, private array $byStatus)
    {
    }

    public function array(): array
    {
        return [
            ...array_map(fn ($row) => ['Source', $row['label'], $row['count']], $this->bySource),
            ...array_map(fn ($row) => ['Status', $row['label'], $row['count']], $this->byStatus),
        ];
    }

    public function headings(): array
    {
        return ['Breakdown', 'Group', 'Bookings'];
    }

    public function title(): string
    {
        return 'Sources and Status';
    }
}
