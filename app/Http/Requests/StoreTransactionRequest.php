<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'type' => 'required|in:INTERNAL,OFFLINE,SHOPEEFOOD,GOFOOD,GRABFOOD',
            'payment_type' => 'required|in:QRIS,CASH,GOPAY,SHOPEEPAY,OVO',
            'status' => 'required|in:PAID,CANCELED',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0'
        ];
    }
}

