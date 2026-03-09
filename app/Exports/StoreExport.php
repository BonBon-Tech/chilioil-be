<?php

namespace App\Exports;

use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StoreExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(protected string $companyId) {}

    public function query()
    {
        return Store::select('stores.*')
            ->selectSub(
                DB::table('products')->whereColumn('products.store_id', 'stores.id')
                    ->whereNull('products.deleted_at')->selectRaw('COUNT(*)'),
                'products_count'
            )
            ->selectSub(
                DB::table('users')->whereColumn('users.store_id', 'stores.id')->selectRaw('COUNT(*)'),
                'users_count'
            )
            ->where('stores.company_id', $this->companyId)
            ->orderBy('stores.name');
    }

    public function headings(): array
    {
        return ['Nama', 'Jumlah Produk', 'Jumlah Karyawan'];
    }

    public function map($row): array
    {
        return [$row->name, $row->products_count ?? 0, $row->users_count ?? 0];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
