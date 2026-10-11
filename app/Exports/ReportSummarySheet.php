<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportSummarySheet implements FromArray, WithHeadings, WithStrictNullComparison, WithTitle
{
    public function __construct(private array $data)
    {
    }

    public function array(): array
    {
        $d = $this->data;

        // Every amount is cast: a SUM() comes back from MySQL as a string,
        // and a string lands in the sheet as text that Excel will not add up.
        return [
            ['Period', $d['from']->format('M d, Y') . ' — ' . $d['to']->format('M d, Y')],
            ['Collected (by date paid)', (float) $d['totalRevenue']],
            ['Refunds', (float) $d['totalRefunds']],
            ['Net Revenue', (float) $d['netRevenue']],
            ['Bookings Made (by date booked)', $d['totalBookings']],
            ['Of those, cancelled', $d['cancelledBookings']],
            ['Of those, no show', $d['noShowBookings']],
            ['Of those, still standing', $d['activeBookings']],
            ['Booked Value (standing bookings)', (float) $d['bookedValue']],
            ['Average Booking Value', round($d['avgBookingValue'], 2)],
            ['Occupancy Rate (%) (by date of stay)', $d['occupancyRate']],
            ['Slots Booked', $d['occupancy']['booked']],
            ['Slots Offered', $d['occupancy']['offered']],
            ['New Guests', $d['newGuests']],
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
