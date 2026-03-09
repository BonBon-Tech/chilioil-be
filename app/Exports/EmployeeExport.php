<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(protected string $companyId) {}

    public function query()
    {
        return User::with(['role', 'assignedStore'])
            ->where('company_id', $this->companyId)
            ->orderBy('name');
    }

    public function headings(): array
    {
        return ['Nama', 'Email', 'Role', 'Toko'];
    }

    public function map($row): array
    {
        return [
            $row->name,
            $row->email,
            $row->role?->name ?? '-',
            $row->assignedStore?->name ?? 'Semua Toko',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
