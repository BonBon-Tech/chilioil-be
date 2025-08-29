<?php

namespace App\Repository;

use App\Models\Transaction;
use App\Models\Product;
use App\Models\CashFlow;
use App\Models\OnlineTransactionDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Jobs\CreateCashFlowJob;

class TransactionRepository
{
    /**
     * @param int $perPage
     * @param array $filters
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = Transaction::with(['transactionItems.product', 'transactionItems.store']);

        // Search by code or customer name
        if (isset($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('code', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('customer_name', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Filter by code
        if (isset($filters['code'])) {
            $query->where('code', 'like', '%' . $filters['code'] . '%');
        }

        // Filter by customer name
        if (isset($filters['customer_name'])) {
            $query->where('customer_name', 'like', '%' . $filters['customer_name'] . '%');
        }

        // Filter by date (exact date)
        if (isset($filters['date'])) {
            $query->whereDate('date', $filters['date']);
        }

        // Filter by date range
        if (isset($filters['start_date'])) {
            $query->whereDate('date', '>=', $filters['start_date']);
        }
        if (isset($filters['from_date'])) {
            $query->whereDate('date', '<=', $filters['from_date']);
        }

        // Filter by total amount range
        if (isset($filters['total_min'])) {
            $query->where('total', '>=', $filters['total_min']);
        }
        if (isset($filters['total_max'])) {
            $query->where('total', '<=', $filters['total_max']);
        }

        // Filter by sub_total range
        if (isset($filters['sub_total_min'])) {
            $query->where('sub_total', '>=', $filters['sub_total_min']);
        }
        if (isset($filters['sub_total_max'])) {
            $query->where('sub_total', '<=', $filters['sub_total_max']);
        }

        // Filter by total_item range
        if (isset($filters['total_item_min'])) {
            $query->where('total_item', '>=', $filters['total_item_min']);
        }
        if (isset($filters['total_item_max'])) {
            $query->where('total_item', '<=', $filters['total_item_max']);
        }

        // Filter by type (single)
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Filter by types (array)
        if (isset($filters['types']) && is_array($filters['types']) && count($filters['types']) > 0) {
            $query->whereIn('type', $filters['types']);
        }

        // Filter by payment_type
        if (isset($filters['payment_type'])) {
            $query->where('payment_type', $filters['payment_type']);
        }

        // Filter by status
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function findById(int $id): ?Transaction
    {
        return Transaction::with(['transactionItems.product', 'transactionItems.store', 'onlineTransactionDetails'])->find($id);
    }

    public function create(array $data): Transaction
    {
        $data['code'] = $this->generateTransactionCode($data['date']);

        $transaction = Transaction::create([
            'code' => $data['code'],
            'date' => $data['date'],
            'customer_name' => $data['customer_name'] ?? null,
            'type' => $data['type'],
            'payment_type' => $data['payment_type'],
            'status' => $data['status'],
            'total' => 0,
            'sub_total' => 0,
            'total_item' => 0,
            'online_transaction_revenue' => $data['online_transaction_revenue'] ?? 0
        ]);

        $this->createTransactionItems($transaction, $data['items']);
        $this->calculateTotals($transaction);

        // Dispatch async online transaction detail creation per store
        $this->dispatchOnlineTransactionDetailCreation($transaction);
        // Async cash flow creation per store
        $this->dispatchCashFlowCreation($transaction);

        return $transaction->load(['transactionItems.product', 'transactionItems.store']);
    }

    public function update(int $id, array $data): ?Transaction
    {
        $transaction = Transaction::find($id);
        if (!$transaction) {
            return null;
        }

        $updateData = array_intersect_key($data, array_flip(['date', 'customer_name', 'type', 'payment_type', 'status']));
        $transaction->update($updateData);

        if (isset($data['items'])) {
            $transaction->transactionItems()->delete();
            $this->createTransactionItems($transaction, $data['items']);
            $this->calculateTotals($transaction);
        }

        // Dispatch async online transaction detail creation per store
        $this->dispatchOnlineTransactionDetailCreation($transaction);
        // Async cash flow creation per store
        $this->dispatchCashFlowCreation($transaction);

        return $transaction->load(['transactionItems.product', 'transactionItems.store']);
    }

    public function delete(int $id): bool
    {
        $transaction = Transaction::find($id);
        if (!$transaction) {
            return false;
        }

        $transaction->transactionItems()->delete();
        return $transaction->delete();
    }

    private function generateTransactionCode(string $date): string
    {
        $dateFormatted = Carbon::parse($date)->format('Ymd');

        return DB::transaction(function () use ($dateFormatted) {
            $lastTransaction = Transaction::whereDate('date', Carbon::parse($dateFormatted)->format('Y-m-d'))
                ->lockForUpdate()
                ->orderBy('id', 'desc')
                ->first();

            if ($lastTransaction && strpos($lastTransaction->code, 'TRX' . $dateFormatted) === 0) {
                $lastSequence = (int) substr($lastTransaction->code, -3);
                $newSequence = $lastSequence + 1;
            } else {
                $newSequence = 1;
            }

            return 'TRX' . $dateFormatted . str_pad($newSequence, 3, '0', STR_PAD_LEFT);
        });
    }

    private function createTransactionItems(Transaction $transaction, array $items): void
    {
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            $totalPrice = $item['price'] * $item['qty'];

            $transaction->transactionItems()->create([
                'product_id' => $item['product_id'],
                'image_path' => $product->image_path,
                'store_id' => $product->store_id,
                'name' => $product->name,
                'code' => $product->code,
                'price' => $item['price'],
                'qty' => $item['qty'],
                'total_price' => $totalPrice,
                'note' => $item['note'] ?? null
            ]);
        }
    }

    private function calculateTotals(Transaction $transaction): void
    {
        $items = $transaction->transactionItems;
        $subTotal = $items->sum('total_price');
        $totalItem = $items->sum('qty');

        $transaction->update([
            'sub_total' => $subTotal,
            'total' => $subTotal, // You can add tax or discount logic here
            'total_item' => $totalItem
        ]);
    }

    /**
     * Dispatch async job to create cash flow per store.
     */
    private function dispatchCashFlowCreation(Transaction $transaction): void
    {
        $storeTotals = [];

        if ($transaction->status !== 'PAID') {
            return;
        }

        if ($transaction->type !== 'OFFLINE') {
            $onlineTransactionDetails = OnlineTransactionDetail::query()->where('transaction_id', $transaction->id)->get();

            foreach ($onlineTransactionDetails as $detail) {
                $storeId = $detail->store_id;
                if (!isset($storeTotals[$storeId])) {
                    $storeTotals[$storeId] = 0;
                }
                $storeTotals[$storeId] += $detail->revenue;
            }

            return;
        } else {
            foreach ($transaction->transactionItems as $item) {
                $storeId = $item->store_id;
                if (!isset($storeTotals[$storeId])) {
                    $storeTotals[$storeId] = 0;
                }
                $storeTotals[$storeId] += $item->total_price;
            }
        }

        foreach ($storeTotals as $storeId => $amount) {
            // Dispatch async job for cash flow creation
            dispatch(new CreateCashFlowJob([
                'type' => CashFlow::TYPE_INCOME,
                'store_id' => $storeId,
                'amount' => $amount,
            ]));
        }
    }

    /**
     * Dispatch async job to create online transaction detail per store.
     */
    private function dispatchOnlineTransactionDetailCreation(Transaction $transaction): void
    {
        if (!in_array($transaction->type, ['SHOPEEFOOD', 'GOFOOD', 'GRABFOOD'])) {
            return;
        }

        if ($transaction->status !== 'PAID') {
            return;
        }

        $storeRevenues = [];
        foreach ($transaction->transactionItems as $item) {
            $storeId = $item->store_id;
            $revenue = $item->price * $item->qty;
            if (!isset($storeRevenues[$storeId])) {
                $storeRevenues[$storeId] = 0;
            }
            $storeRevenues[$storeId] += $revenue;
        }

        foreach ($storeRevenues as $storeId => $revenue) {
            $percentage = round(($revenue / $transaction->total) * 100);
            $actualRevenue = round(($transaction->online_transaction_revenue * $percentage) / 100);

            OnlineTransactionDetail::create([
                'transaction_id' => $transaction->id,
                'store_id' => $storeId,
                'revenue' => $actualRevenue,
            ]);
        }
    }
}
