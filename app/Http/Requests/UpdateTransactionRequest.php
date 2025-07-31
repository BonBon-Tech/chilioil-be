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
            'type' => 'sometimes|in:INTERNAL,OFFLINE,SHOPEEFOOD,GOFOOD,GRABFOOD',
            'payment_type' => 'sometimes|in:QRIS,CASH,GOPAY,SHOPEEPAY,OVO',
            'status' => 'sometimes|in:PAID,CANCELED',
            'items' => 'sometimes|array|min:1',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.qty' => 'required_with:items|integer|min:1',
            'items.*.price' => 'required_with:items|numeric|min:0'
        ];
    }
}

