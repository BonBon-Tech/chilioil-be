<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesTransactionAmounts;
use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
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
            'date' => 'sometimes|date',
            'customer_name' => 'sometimes|nullable|string|max:255',
            'type' => 'sometimes|in:INTERNAL,OFFLINE,SHOPEEFOOD,GOFOOD,GRABFOOD',
            'payment_type' => 'sometimes|required|in:QRIS,CASH,GOPAY,SHOPEEPAY,OVO,BANK_TRANSFER',
            'status' => 'sometimes|in:PAID,CANCELED,PENDING',
            'items' => 'sometimes|array|list|min:1',
            'items.*.product_id' => [
                'required_with:items',
                Rule::exists('products', 'id')->where(fn($query) => $query
                    ->whereNull('deleted_at')
                    ->whereIn('store_id', Store::query()
                        ->select('id')
                        ->where('company_id', $companyId)
                        ->when($isStaff, fn($stores) => $stores->where('id', $storeId)))),
            ],
            'items.*.note' => 'nullable|string|max:500'
        ] + $this->transactionAmountRules('required_with:items');
    }
}
