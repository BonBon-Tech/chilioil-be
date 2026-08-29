<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;
        $storeId = $this->input('store_id');

        return [
            'store_id' => ['required', 'uuid', Rule::exists('stores', 'id')->where('company_id', $companyId)],
            'date' => 'required|date|before_or_equal:today',
            'account_id' => ['required', 'uuid', Rule::exists('cash_accounts', 'id')->where('company_id', $companyId)->where('store_id', $storeId)->where('is_active', true)],
            'actual_balance' => 'required|numeric|min:0',
            'note' => 'required|string|max:500',
        ];
    }
}
