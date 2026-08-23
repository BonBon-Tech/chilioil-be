<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDailyReportRestockRequest extends FormRequest
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
            'creditor_id' => ['required', 'uuid', Rule::exists('daily_report_creditors', 'id')
                ->where('company_id', $companyId)->where('store_id', $storeId)->where('is_active', true)],
            'items' => 'present|array|max:100',
            'items.*.name' => 'required|string|max:150',
            'items.*.quantity' => 'nullable|numeric|gt:0',
            'items.*.unit' => 'nullable|required_with:items.*.quantity|string|max:30',
            'items.*.expense_category_id' => ['required', 'uuid', Rule::exists('expense_categories', 'id')->where('company_id', $companyId)],
            'items.*.amount' => 'required|numeric|gt:0',
        ];
    }
}
