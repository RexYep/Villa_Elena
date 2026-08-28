<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportTopPropertiesSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private Collection $topProperties)
    {
    }

    public function collection(): Collection
    {
        return $this->topProperties;
    }

    public function map($row): array
    {
        return [
            $row->property->property_name ?? 'N/A',
            $row->bookings,
            $row->revenue,
            $row->bookings > 0 ? round($row->revenue / $row->bookings, 2) : 0,
        ];
    }

    public function headings(): array
    {
        return ['Property', 'Bookings', 'Revenue', 'Avg / Booking'];
    }

    public function title(): string
    {
        return 'Top Properties';
    }
}
