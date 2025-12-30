<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PhysicalReportExport implements WithMultipleSheets
{
    protected $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function sheets(): array
    {
        $sheets = [];

        // Hoja 1: Ventas
        $sheets['Ventas'] = new PhysicalSalesExport($this->filters);
        // Hoja 2: Gastos
        $sheets['Gastos'] = new ExpensesExport($this->filters);

        return $sheets;
    }
}
