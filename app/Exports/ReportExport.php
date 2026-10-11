<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReportExport implements WithMultipleSheets
{
    public function __construct(private array $data)
    {
    }

    public function sheets(): array
    {
        return [
            'Summary'               => new ReportSummarySheet($this->data),
            'Net Revenue in Period' => new ReportPeriodRevenueSheet($this->data['revenueSeries'], $this->data['revenueUnit']),
            'Revenue by Month'      => new ReportRevenueByMonthSheet($this->data['revenueByMonth']),
            'Bookings by Slot'      => new ReportBySlotSheet($this->data['bySlot']),
            'Sources and Status'    => new ReportBreakdownSheet($this->data['bookingsBySource'], $this->data['bookingsByStatus']),
        ];
    }
}
