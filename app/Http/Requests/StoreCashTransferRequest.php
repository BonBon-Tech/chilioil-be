<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;
        $storeId = $this->input('store_id');
        $account = fn () => Rule::exists('cash_accounts', 'id')->where('company_id', $companyId)->where('store_id', $storeId)->where('is_active', true);

        return [
            'store_id' => ['required', 'uuid', Rule::exists('stores', 'id')->where('company_id', $companyId)],
            'date' => 'required|date|before_or_equal:today',
            'source_account_id' => ['required', 'uuid', $account()],
            'destination_account_id' => ['required', 'uuid', 'different:source_account_id', $account()],
            'amount' => 'required|numeric|gt:0',
            'admin_fee' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:500',
        ];
    }
}
