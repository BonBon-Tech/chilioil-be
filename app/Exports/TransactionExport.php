<?php

namespace App\Exports;

use App\Models\Transaction;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TransactionExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        protected string  $companyId,
        protected ?string $startDate  = null,
        protected ?string $endDate    = null,
        protected ?string $status     = null,
        protected ?string $type       = null,
        protected ?string $storeId    = null,
    ) {}

    public function query()
    {
        $query = Transaction::where('company_id', $this->companyId)
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

        if ($this->startDate && $this->endDate) {
            $query->whereBetween('date', [$this->startDate, $this->endDate]);
        }
        if ($this->status) {
            $query->where('status', $this->status);
        }
        if ($this->type) {
            $query->where('type', $this->type);
        }
        if ($this->storeId) {
            $query->where('store_id', $this->storeId);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Kode',
            'Tanggal',
            'Pelanggan',
            'Tipe',
            'Pembayaran',
            'Status',
            'Jumlah Item',
            'Subtotal',
            'Total',
            'Pendapatan Online',
            'Toko',
        ];
    }

    public function map($row): array
    {
        return [
            $row->code,
            $row->date?->format('Y-m-d'),
            $row->customer_name ?? '-',
            $row->type,
            $row->payment_type,
            $row->status,
            $row->total_item,
            (float) $row->sub_total,
            (float) $row->total,
            (float) $row->online_transaction_revenue,
            '-', // store not stored on transaction directly
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
