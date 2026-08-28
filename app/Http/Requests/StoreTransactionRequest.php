<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesTransactionAmounts;
use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    use ValidatesTransactionAmounts;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        $companyId = $user?->company_id;
        $isStaff = $user?->role?->name === 'staff';
        $storeId = $user?->store_id;

        return [
            'date' => 'required|date',
            'customer_name' => 'nullable|string|max:255',
            'type' => 'required|in:INTERNAL,OFFLINE,SHOPEEFOOD,GOFOOD,GRABFOOD',
            'payment_type' => 'required|in:QRIS,CASH,GOPAY,SHOPEEPAY,OVO,BANK_TRANSFER',
            'status' => 'required|in:PAID,CANCELED,PENDING',
            'items' => 'required|array|list|min:1',
            'items.*.product_id' => [
                'required',
                Rule::exists('products', 'id')->where(fn($query) => $query
                    ->whereNull('deleted_at')
                    ->whereIn('store_id', Store::query()
                        ->select('id')
                        ->where('company_id', $companyId)
                        ->when($isStaff, fn($stores) => $stores->where('id', $storeId)))),
            ],
            'items.*.note' => 'nullable|string|max:500',
            'online_transaction_revenue' => 'nullable|numeric',
        ] + $this->transactionAmountRules('required');
    }
}
