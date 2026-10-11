<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

// WithStrictNullComparison, here and on the other report sheets: without it a
// month that collected nothing is written as an empty cell instead of 0.
class ReportRevenueByMonthSheet implements FromArray, WithHeadings, WithStrictNullComparison, WithTitle
{
    public function __construct(private array $revenueByMonth)
    {
    }

    public function array(): array
    {
        return array_map(fn ($row) => [$row['label'], $row['amount']], $this->revenueByMonth);
    }

    public function headings(): array
    {
        return ['Month', 'Net Revenue (after refunds)'];
    }

    public function title(): string
    {
        return 'Revenue by Month';
    }
}
