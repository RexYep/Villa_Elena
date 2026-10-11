<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportBySlotSheet implements FromArray, WithHeadings, WithStrictNullComparison, WithTitle
{
    public function __construct(private array $bySlot)
    {
    }

    public function array(): array
    {
        return array_map(fn ($row) => [
            $row['label'],
            $row['times'],
            $row['bookings'],
            round($row['value'], 2),
            round($row['average'], 2),
            $row['share'],
        ], $this->bySlot);
    }

    public function headings(): array
    {
        return ['Slot', 'Hours', 'Bookings', 'Booked Value', 'Avg / Booking', 'Share of Booked Value (%)'];
    }

    public function title(): string
    {
        return 'Bookings by Slot';
    }
}
