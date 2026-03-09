<?php

namespace App\Exports;

use App\Models\ProductCategory;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductCategoryExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(protected string $companyId) {}

    public function query()
    {
        return ProductCategory::withCount('products')
            ->where('company_id', $this->companyId)
            ->orderBy('name');
    }

    public function headings(): array
    {
        return ['Nama', 'Jumlah Produk'];
    }

    public function map($row): array
    {
        return [$row->name, $row->products_count];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
