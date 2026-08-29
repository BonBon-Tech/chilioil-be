<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashAccountSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'uuid', Rule::exists('stores', 'id')->where('company_id', $this->user()?->company_id)],
            'opening_date' => 'required|date|before_or_equal:today',
            'balances' => 'required|array:bca,mandiri,cash',
            'balances.bca' => 'required|numeric|min:0',
            'balances.mandiri' => 'required|numeric|min:0',
            'balances.cash' => 'required|numeric|min:0',
        ];
    }
}
