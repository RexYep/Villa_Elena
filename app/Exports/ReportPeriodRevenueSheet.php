<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/** The period chart as rows. The rows add up to Net Revenue on the Summary sheet. */
class ReportPeriodRevenueSheet implements FromArray, WithHeadings, WithStrictNullComparison, WithTitle
{
    private const UNIT_HEADINGS = ['day' => 'Day', 'week' => 'Week Starting', 'month' => 'Month'];

    public function __construct(private array $series, private string $unit)
    {
    }

    public function array(): array
    {
        return array_map(fn ($row) => [$row['label'], $row['amount']], $this->series);
    }

    public function headings(): array
    {
        return [self::UNIT_HEADINGS[$this->unit] ?? 'Period', 'Net Revenue (after refunds)'];
    }

    public function title(): string
    {
        return 'Net Revenue in Period';
    }
}
