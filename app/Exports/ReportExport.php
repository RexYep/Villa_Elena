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
            'Summary'          => new ReportSummarySheet($this->data),
            'Revenue by Month' => new ReportRevenueByMonthSheet($this->data['revenueByMonth']),
            'Top Properties'   => new ReportTopPropertiesSheet($this->data['topProperties']),
        ];
    }
}
