<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDailyReportDebtRequest extends FormRequest
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
            'payments' => 'required|array|max:100',
            'payments.*.creditor_id' => ['required', 'uuid', 'distinct', Rule::exists('daily_report_creditors', 'id')
                ->where('company_id', $companyId)->where('store_id', $storeId)],
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.note' => 'nullable|string|max:500',
        ];
    }
}
