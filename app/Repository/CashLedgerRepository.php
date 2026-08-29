<?php

namespace App\Repository;

use App\Models\CashAccount;
use App\Models\CashChannelMapping;
use App\Models\CashLedgerEntry;
use App\Models\DailyReport;
use App\Models\DailyReportDebtPayment;
use App\Models\Expense;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CashLedgerRepository
{
    private const DEFAULT_ACCOUNTS = ['bca' => 'BCA', 'mandiri' => 'Mandiri', 'cash' => 'Cash'];

    private const DEFAULT_MAPPINGS = [
        'shopeefood' => 'mandiri',
        'grabfood' => 'bca',
        'gofood' => 'mandiri',
        'qris' => 'mandiri',
        'cash' => 'cash',
    ];

    public function ensureDefaults(string $companyId, string $storeId): Collection
    {
        foreach (self::DEFAULT_ACCOUNTS as $code => $name) {
            CashAccount::firstOrCreate(
                ['company_id' => $companyId, 'store_id' => $storeId, 'code' => $code],
                ['name' => $name, 'is_active' => true],
            );
        }

        $accounts = CashAccount::where('company_id', $companyId)->where('store_id', $storeId)->get()->keyBy('code');
        foreach (self::DEFAULT_MAPPINGS as $channel => $code) {
            CashChannelMapping::firstOrCreate(
                ['company_id' => $companyId, 'store_id' => $storeId, 'channel' => $channel],
                ['cash_account_id' => $accounts[$code]->id],
            );
        }

        return $accounts->values();
    }

    public function setup(string $companyId, string $storeId, string $date, array $balances, ?string $userId): array
    {
        return DB::transaction(function () use ($companyId, $storeId, $date, $balances, $userId) {
            $accounts = $this->ensureDefaults($companyId, $storeId)->keyBy('code');
            $this->lockAccounts($companyId, $storeId, $accounts->pluck('id')->all());
            if ($accounts->contains(fn (CashAccount $account) => $account->opened_on !== null)) {
                throw ValidationException::withMessages(['accounts' => 'Saldo awal sudah pernah diatur. Gunakan koreksi saldo.']);
            }

            foreach (self::DEFAULT_ACCOUNTS as $code => $_) {
                $account = $accounts[$code];
                $account->update(['opened_on' => $date]);
                $amount = (float) ($balances[$code] ?? 0);
                if ($amount > 0) {
                    CashLedgerEntry::create([
                        'company_id' => $companyId,
                        'store_id' => $storeId,
                        'entry_date' => $date,
                        'type' => 'opening',
                        'destination_account_id' => $account->id,
                        'amount' => $amount,
                        'reference_type' => 'cash_account',
                        'reference_id' => $account->id,
                        'reference_key' => 'opening',
                        'note' => 'Saldo awal',
                        'created_by' => $userId,
                    ]);
                }
            }

            return $this->overview($companyId, $storeId);
        });
    }

    public function overview(string $companyId, string $storeId, ?string $date = null): array
    {
        $accounts = $this->ensureDefaults($companyId, $storeId)
            ->sortBy(function (CashAccount $account) {
                $position = array_search($account->code, array_keys(self::DEFAULT_ACCOUNTS), true);

                return $position === false ? 99 : $position;
            })
            ->values()
            ->map(function (CashAccount $account) use ($companyId, $storeId, $date) {
                $row = [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'opened_on' => $account->opened_on?->format('Y-m-d'),
                    'is_active' => $account->is_active,
                    'balance' => $this->balance($companyId, $storeId, $account->id, $date),
                ];
                if ($date) {
                    $movement = $this->movement($companyId, $storeId, $account->id, $date);
                    $row += [
                        'opening_balance' => $this->balanceBefore($companyId, $storeId, $account->id, $date),
                        'incoming' => $movement['incoming'],
                        'outgoing' => $movement['outgoing'],
                        'closing_balance' => $row['balance'],
                    ];
                }

                return $row;
            });

        $mappings = CashChannelMapping::where('company_id', $companyId)
            ->where('store_id', $storeId)
            ->pluck('cash_account_id', 'channel')
            ->all();

        $result = [
            'accounts' => $accounts->all(),
            'mappings' => $mappings,
            'total' => (float) $accounts->sum('balance'),
            'is_initialized' => $accounts->whereNotNull('opened_on')->count() === $accounts->count(),
        ];
        if ($date) {
            $names = $accounts->pluck('name', 'id');
            $result['entries'] = CashLedgerEntry::where('company_id', $companyId)->where('store_id', $storeId)
                ->whereDate('entry_date', $date)->orderBy('created_at')->orderBy('id')->get()
                ->map(fn (CashLedgerEntry $entry) => [
                    'id' => $entry->id,
                    'type' => $entry->type,
                    'amount' => (float) $entry->amount,
                    'source_account_id' => $entry->source_account_id,
                    'source_account_name' => $names[$entry->source_account_id] ?? null,
                    'destination_account_id' => $entry->destination_account_id,
                    'destination_account_name' => $names[$entry->destination_account_id] ?? null,
                    'note' => $entry->note,
                ])->all();
        }

        return $result;
    }

    public function createAccount(string $companyId, string $storeId, array $data, string $userId): array
    {
        return DB::transaction(function () use ($companyId, $storeId, $data, $userId) {
            $base = Str::slug($data['name']);
            $code = $base;
            $suffix = 2;
            while (CashAccount::where('company_id', $companyId)->where('store_id', $storeId)->where('code', $code)->exists()) {
                $code = $base.'-'.$suffix++;
            }
            $account = CashAccount::create([
                'company_id' => $companyId,
                'store_id' => $storeId,
                'code' => $code,
                'name' => $data['name'],
                'opened_on' => $data['opening_date'],
                'is_active' => true,
            ]);
            if ((float) $data['opening_balance'] > 0) {
                CashLedgerEntry::create([
                    'company_id' => $companyId, 'store_id' => $storeId,
                    'entry_date' => $data['opening_date'], 'type' => 'opening',
                    'destination_account_id' => $account->id, 'amount' => $data['opening_balance'],
                    'reference_type' => 'cash_account', 'reference_id' => $account->id,
                    'reference_key' => 'opening', 'note' => 'Saldo awal', 'created_by' => $userId,
                ]);
            }

            return collect($this->overview($companyId, $storeId)['accounts'])->firstWhere('id', $account->id);
        });
    }

    public function updateMappings(string $companyId, string $storeId, array $mappings): array
    {
        return DB::transaction(function () use ($companyId, $storeId, $mappings) {
            foreach (self::DEFAULT_MAPPINGS as $channel => $_) {
                $account = CashAccount::where('company_id', $companyId)->where('store_id', $storeId)
                    ->where('is_active', true)->whereNotNull('opened_on')->findOrFail($mappings[$channel]);
                CashChannelMapping::updateOrCreate(
                    ['company_id' => $companyId, 'store_id' => $storeId, 'channel' => $channel],
                    ['cash_account_id' => $account->id],
                );
            }

            return $this->overview($companyId, $storeId);
        });
    }

    public function updateAccount(string $companyId, string $storeId, string $id, array $data): array
    {
        $account = CashAccount::where('company_id', $companyId)->where('store_id', $storeId)->findOrFail($id);
        if (($data['is_active'] ?? true) === false && CashChannelMapping::where('cash_account_id', $account->id)->exists()) {
            throw ValidationException::withMessages(['is_active' => 'Pindahkan mapping sales sebelum menonaktifkan akun.']);
        }
        $account->update(['name' => $data['name'], 'is_active' => $data['is_active']]);

        return collect($this->overview($companyId, $storeId)['accounts'])->firstWhere('id', $account->id);
    }

    public function history(string $companyId, string $storeId, int $perPage): LengthAwarePaginator
    {
        $paginator = CashLedgerEntry::query()->where('company_id', $companyId)->where('store_id', $storeId)
            ->select('entry_date')->distinct()->orderByDesc('entry_date')->paginate($perPage);

        return $paginator->through(fn ($row) => [
            'id' => null,
            'report_date' => $row->entry_date instanceof \DateTimeInterface ? $row->entry_date->format('Y-m-d') : (string) $row->entry_date,
            'store_id' => $storeId,
            'restock' => ['creditor' => null, 'items' => [], 'total' => 0],
            'expenses' => ['items' => [], 'total' => 0],
            'debt' => [],
            'income' => ['source' => (object) [], 'reported' => (object) [], 'total' => 0],
            'balance' => $this->overview($companyId, $storeId, $row->entry_date instanceof \DateTimeInterface ? $row->entry_date->format('Y-m-d') : (string) $row->entry_date),
        ]);
    }

    public function syncIncome(DailyReport $report, array $amounts, ?string $userId): void
    {
        $this->ensureDefaults($report->company_id, $report->store_id);
        $mappings = collect($report->cash_mapping_snapshot);
        if ($mappings->isEmpty()) {
            $mappings = CashChannelMapping::where('company_id', $report->company_id)
                ->where('store_id', $report->store_id)
                ->pluck('cash_account_id', 'channel');
            $report->update(['cash_mapping_snapshot' => $mappings->all()]);
        }
        $existingAccountIds = CashLedgerEntry::where('reference_type', 'daily_report_income')
            ->where('reference_id', $report->id)->pluck('destination_account_id')->all();
        $this->lockAccounts(
            $report->company_id,
            $report->store_id,
            array_merge($mappings->values()->all(), $existingAccountIds),
        );

        foreach ($amounts as $channel => $amount) {
            $existing = CashLedgerEntry::where('reference_type', 'daily_report_income')
                ->where('reference_id', $report->id)->where('reference_key', $channel)->first();
            $accountId = $existing?->destination_account_id ?? $mappings[$channel] ?? null;
            $account = $accountId ? $this->account($report->company_id, $report->store_id, $accountId) : null;
            if (! $account || ! $account->opened_on || $account->opened_on->format('Y-m-d') > $report->report_date->format('Y-m-d')) {
                continue;
            }
            if ((float) $amount === 0.0) {
                $existing?->delete();

                continue;
            }
            CashLedgerEntry::updateOrCreate(
                ['reference_type' => 'daily_report_income', 'reference_id' => $report->id, 'reference_key' => $channel],
                [
                    'company_id' => $report->company_id,
                    'store_id' => $report->store_id,
                    'entry_date' => $report->report_date,
                    'type' => 'income',
                    'source_account_id' => null,
                    'destination_account_id' => $accountId,
                    'amount' => $amount,
                    'note' => $channel,
                    'created_by' => $userId,
                ],
            );
        }

        $this->assertAccountsNeverNegative($report->company_id, $report->store_id, $mappings->values()->all());
    }

    public function syncExpense(Expense $expense, array $allocations, ?string $userId): void
    {
        $this->lockAccounts(
            $expense->company_id,
            $expense->store_id,
            collect($allocations)->pluck('account_id')->all(),
        );
        CashLedgerEntry::where('reference_type', 'expense')->where('reference_id', $expense->id)->delete();
        foreach ($allocations as $allocation) {
            $account = $this->activeAccount($expense->company_id, $expense->store_id, $allocation['account_id'], $expense->date->format('Y-m-d'));
            CashLedgerEntry::create([
                'company_id' => $expense->company_id,
                'store_id' => $expense->store_id,
                'entry_date' => $expense->date,
                'type' => 'expense',
                'source_account_id' => $account->id,
                'amount' => $allocation['amount'],
                'reference_type' => 'expense',
                'reference_id' => $expense->id,
                'reference_key' => $account->id,
                'note' => $expense->description,
                'created_by' => $userId,
            ]);
        }
        $this->assertAccountsNeverNegative($expense->company_id, $expense->store_id, collect($allocations)->pluck('account_id')->all());
    }

    public function syncDebtPayment(DailyReport $report, DailyReportDebtPayment $payment, string $accountId, ?string $userId): void
    {
        $this->lockAccounts($report->company_id, $report->store_id, [$accountId]);
        $account = $this->activeAccount($report->company_id, $report->store_id, $accountId, $report->report_date->format('Y-m-d'));
        CashLedgerEntry::updateOrCreate(
            ['reference_type' => 'debt_payment', 'reference_id' => $payment->id, 'reference_key' => 'payment'],
            [
                'company_id' => $report->company_id,
                'store_id' => $report->store_id,
                'entry_date' => $report->report_date,
                'type' => 'debt_payment',
                'source_account_id' => $account->id,
                'destination_account_id' => null,
                'amount' => $payment->amount,
                'note' => $payment->note,
                'created_by' => $userId,
            ],
        );
        $this->assertAccountsNeverNegative($report->company_id, $report->store_id, [$account->id]);
    }

    public function transfer(string $companyId, string $storeId, array $data, string $userId): CashLedgerEntry
    {
        return DB::transaction(function () use ($companyId, $storeId, $data, $userId) {
            $ids = [$data['source_account_id'], $data['destination_account_id']];
            CashAccount::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $source = $this->activeAccount($companyId, $storeId, $ids[0], $data['date']);
            $destination = $this->activeAccount($companyId, $storeId, $ids[1], $data['date']);
            if ($source->id === $destination->id) {
                throw ValidationException::withMessages(['destination_account_id' => 'Akun tujuan harus berbeda.']);
            }
            $fee = (float) ($data['admin_fee'] ?? 0);
            if ($this->balance($companyId, $storeId, $source->id, $data['date']) < (float) $data['amount'] + $fee) {
                throw ValidationException::withMessages(['amount' => 'Saldo akun sumber tidak mencukupi.']);
            }

            $transfer = CashLedgerEntry::create([
                'company_id' => $companyId,
                'store_id' => $storeId,
                'entry_date' => $data['date'],
                'type' => 'transfer',
                'source_account_id' => $source->id,
                'destination_account_id' => $destination->id,
                'amount' => $data['amount'],
                'note' => $data['note'] ?? null,
                'created_by' => $userId,
            ]);
            if ($fee > 0) {
                CashLedgerEntry::create([
                    'company_id' => $companyId,
                    'store_id' => $storeId,
                    'entry_date' => $data['date'],
                    'type' => 'transfer_fee',
                    'source_account_id' => $source->id,
                    'amount' => $fee,
                    'reference_type' => 'cash_transfer',
                    'reference_id' => $transfer->id,
                    'reference_key' => 'admin_fee',
                    'note' => "Biaya admin {$source->name} → {$destination->name}",
                    'created_by' => $userId,
                ]);
            }

            return $transfer;
        });
    }

    public function adjust(string $companyId, string $storeId, array $data, string $userId): CashLedgerEntry
    {
        return DB::transaction(function () use ($companyId, $storeId, $data, $userId) {
            CashAccount::whereKey($data['account_id'])->lockForUpdate()->first();
            $account = $this->activeAccount($companyId, $storeId, $data['account_id'], $data['date']);
            $current = $this->balance($companyId, $storeId, $account->id, $data['date']);
            $delta = (float) $data['actual_balance'] - $current;
            if ($delta === 0.0) {
                throw ValidationException::withMessages(['actual_balance' => 'Saldo aktual sama dengan saldo sistem.']);
            }

            $entry = CashLedgerEntry::create([
                'company_id' => $companyId,
                'store_id' => $storeId,
                'entry_date' => $data['date'],
                'type' => 'adjustment',
                'source_account_id' => $delta < 0 ? $account->id : null,
                'destination_account_id' => $delta > 0 ? $account->id : null,
                'amount' => abs($delta),
                'note' => $data['note'],
                'created_by' => $userId,
            ]);
            $this->assertAccountsNeverNegative($companyId, $storeId, [$account->id]);

            return $entry;
        });
    }

    public function balance(string $companyId, string $storeId, string $accountId, ?string $date = null): float
    {
        $query = CashLedgerEntry::where('company_id', $companyId)->where('store_id', $storeId);
        if ($date) {
            $query->whereDate('entry_date', '<=', $date);
        }
        $incoming = (float) (clone $query)->where('destination_account_id', $accountId)->sum('amount');
        $outgoing = (float) (clone $query)->where('source_account_id', $accountId)->sum('amount');

        return $incoming - $outgoing;
    }

    private function balanceBefore(string $companyId, string $storeId, string $accountId, string $date): float
    {
        $query = CashLedgerEntry::where('company_id', $companyId)->where('store_id', $storeId)->whereDate('entry_date', '<', $date);

        return (float) (clone $query)->where('destination_account_id', $accountId)->sum('amount')
            - (float) (clone $query)->where('source_account_id', $accountId)->sum('amount');
    }

    private function movement(string $companyId, string $storeId, string $accountId, string $date): array
    {
        $query = CashLedgerEntry::where('company_id', $companyId)->where('store_id', $storeId)->whereDate('entry_date', $date);

        return [
            'incoming' => (float) (clone $query)->where('destination_account_id', $accountId)->sum('amount'),
            'outgoing' => (float) (clone $query)->where('source_account_id', $accountId)->sum('amount'),
        ];
    }

    private function account(string $companyId, string $storeId, string $id): ?CashAccount
    {
        return CashAccount::where('company_id', $companyId)->where('store_id', $storeId)->find($id);
    }

    private function activeAccount(string $companyId, string $storeId, string $id, string $date): CashAccount
    {
        $account = CashAccount::where('company_id', $companyId)->where('store_id', $storeId)
            ->where('is_active', true)->findOrFail($id);
        if (! $account->opened_on || $account->opened_on->format('Y-m-d') > $date) {
            throw ValidationException::withMessages(['account_id' => 'Akun belum aktif pada tanggal tersebut.']);
        }

        return $account;
    }

    private function assertAccountsNeverNegative(string $companyId, string $storeId, array $accountIds): void
    {
        foreach (array_unique(array_filter($accountIds)) as $accountId) {
            $running = 0.0;
            $rows = CashLedgerEntry::where('company_id', $companyId)->where('store_id', $storeId)
                ->where(fn ($query) => $query->where('source_account_id', $accountId)->orWhere('destination_account_id', $accountId))
                ->orderBy('entry_date')->orderBy('created_at')->orderBy('id')->get();
            foreach ($rows as $row) {
                $running += $row->destination_account_id === $accountId ? (float) $row->amount : -(float) $row->amount;
                if ($running < 0) {
                    throw ValidationException::withMessages(['balance' => 'Transaksi membuat saldo akun menjadi negatif.']);
                }
            }
        }
    }

    private function lockAccounts(string $companyId, string $storeId, array $accountIds): void
    {
        CashAccount::where('company_id', $companyId)->where('store_id', $storeId)
            ->whereIn('id', array_unique(array_filter($accountIds)))
            ->orderBy('id')->lockForUpdate()->get();
    }
}
