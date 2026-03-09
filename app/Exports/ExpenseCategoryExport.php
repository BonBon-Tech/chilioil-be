<?php

namespace App\Exports;

use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExpenseCategoryExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(protected string $companyId) {}

    public function query()
    {
        return ExpenseCategory::select('expense_categories.*')
            ->selectSub(
                DB::table('expenses')
                    ->whereColumn('expenses.expense_category_id', 'expense_categories.id')
                    ->whereNull('expenses.deleted_at')
                    ->selectRaw('COUNT(*)'),
                'expenses_count'
            )
            ->where('expense_categories.company_id', $this->companyId)
            ->orderBy('expense_categories.name');
    }

    public function headings(): array
    {
        return ['Nama', 'Jumlah Pengeluaran'];
    }

    public function map($row): array
    {
        return [$row->name, $row->expenses_count ?? 0];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
