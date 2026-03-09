<?php

namespace App\Exports;

use App\Models\Expense;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExpenseExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        protected string  $companyId,
        protected ?string $startDate  = null,
        protected ?string $endDate    = null,
        protected ?string $storeId    = null,
        protected ?string $categoryId = null,
    ) {}

    public function query()
    {
        $query = Expense::with(['expenseCategory'])
            ->where('company_id', $this->companyId)
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

        if ($this->startDate && $this->endDate) {
            $query->whereBetween('date', [$this->startDate, $this->endDate]);
        }
        if ($this->storeId) {
            $query->where('store_id', $this->storeId);
        }
        if ($this->categoryId) {
            $query->where('expense_category_id', $this->categoryId);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Kategori',
            'Jumlah (Rp)',
            'Referensi',
            'Deskripsi',
            'Toko',
        ];
    }

    public function map($row): array
    {
        return [
            $row->date?->format('Y-m-d'),
            $row->expenseCategory?->name ?? '-',
            (float) $row->amount,
            $row->reference ?? '-',
            $row->description ?? '-',
            '-', // store not stored on expense directly
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
