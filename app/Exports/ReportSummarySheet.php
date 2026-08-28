<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportSummarySheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(private array $data)
    {
    }

    public function array(): array
    {
        $d = $this->data;

        return [
            ['Period', $d['from']->format('M d, Y') . ' — ' . $d['to']->format('M d, Y')],
            ['Net Revenue', $d['netRevenue']],
            ['Total Refunds', $d['totalRefunds']],
            ['Total Bookings', $d['totalBookings']],
            ['Confirmed Bookings', $d['confirmedBookings']],
            ['Cancelled Bookings', $d['cancelledBookings']],
            ['New Guests', $d['newGuests']],
            ['Occupancy Rate (%)', $d['occupancyRate']],
            ['Average Booking Value', round($d['avgBookingValue'], 2)],
        ];
    }

    public function headings(): array
    {
        return ['Metric', 'Value'];
    }

    public function title(): string
    {
        return 'Summary';
    }
}
