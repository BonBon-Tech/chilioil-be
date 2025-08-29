<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'sometimes|date',
            'customer_name' => 'sometimes|nullable|string|max:255',
            'type' => 'sometimes|in:INTERNAL,OFFLINE,SHOPEEFOOD,GOFOOD,GRABFOOD',
            'payment_type' => 'sometimes|required|in:QRIS,CASH,GOPAY,SHOPEEPAY,OVO,BANK_TRANSFER',
            'status' => 'sometimes|in:PAID,CANCELED,PENDING',
            'items' => 'sometimes|array|min:1',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.qty' => 'required_with:items|integer|min:1',
            'items.*.price' => 'required_with:items|numeric|min:0',
            'items.*.note' => 'nullable|string|max:500'
        ];
    }
}
