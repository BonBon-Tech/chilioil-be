<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        protected string  $companyId,
        protected ?string $storeId      = null,
        protected ?string $sellingType  = null,
    ) {}

    public function query()
    {
        $query = Product::with(['store', 'productCategory'])
            ->whereHas('store', fn($q) => $q->where('company_id', $this->companyId))
            ->orderBy('name');

        if ($this->storeId) {
            $query->where('store_id', $this->storeId);
        }
        if ($this->sellingType) {
            $query->where('selling_type', $this->sellingType);
        }

        return $query;
    }

    public function headings(): array
    {
        return ['Kode', 'Nama', 'Tipe', 'Kategori', 'Harga', 'Toko', 'Status'];
    }

    public function map($row): array
    {
        return [
            $row->code,
            $row->name,
            $row->selling_type,
            $row->productCategory?->name ?? '-',
            (float) $row->price,
            $row->store?->name ?? '-',
            $row->status ? 'Aktif' : 'Nonaktif',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
