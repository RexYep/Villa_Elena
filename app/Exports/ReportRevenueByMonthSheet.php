<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportRevenueByMonthSheet implements FromArray, WithHeadings, WithTitle
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
        return ['Month', 'Revenue'];
    }

    public function title(): string
    {
        return 'Revenue by Month';
    }
}
