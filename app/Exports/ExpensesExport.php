<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\Auth;

use Maatwebsite\Excel\Concerns\WithTitle;

class ExpensesExport implements FromCollection, WithHeadings, WithTitle
{
    protected $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function collection(): Collection
    {
        $query = Auth::user()->store->expenses()->with('user');
        
        if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
            $appTz = config('app.timezone', 'America/Bogota');
            $startLocal = \Carbon\Carbon::parse($this->filters['start_date'], $appTz)->startOfDay();
            $endLocal = \Carbon\Carbon::parse($this->filters['end_date'], $appTz)->endOfDay();
            
            $query->whereRaw(
                "DATE(CONVERT_TZ(expense_date, 'UTC', ?)) BETWEEN ? AND ?",
                [$appTz, $startLocal->toDateString(), $endLocal->toDateString()]
            );
        }
        
        $expenses = $query->orderByDesc('expense_date')->get();

        return $expenses->map(function ($expense) {
            return [
                $expense->id,
                $expense->expense_date->timezone(config('app.timezone', 'America/Bogota'))->format('Y-m-d H:i'),
                $expense->amount,
                $expense->description,
                $expense->user->name ?? 'N/A',
                $expense->created_at->timezone(config('app.timezone', 'America/Bogota'))->format('Y-m-d H:i'),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Fecha del Gasto',
            'Monto',
            'Descripción',
            'Registrado Por',
            'Fecha de Registro',
        ];
    }
    public function title(): string
    {
        return 'Gastos y Salidas';
    }
}
