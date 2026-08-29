<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDailyReportExpenseRequest extends FormRequest
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
            'items' => 'present|array|max:100',
            'items.*.name' => 'required|string|max:150',
            'items.*.quantity' => 'nullable|numeric|gt:0',
            'items.*.unit' => 'nullable|required_with:items.*.quantity|string|max:30',
            'items.*.expense_category_id' => ['required', 'uuid', Rule::exists('expense_categories', 'id')->where('company_id', $companyId)],
            'items.*.amount' => 'required|numeric|gt:0',
            'items.*.creditor_id' => ['nullable', 'uuid', Rule::exists('daily_report_creditors', 'id')->where('company_id', $companyId)->where('store_id', $storeId)->where('is_active', true)],
            'items.*.allocations' => 'present|array|max:20',
            'items.*.allocations.*.account_id' => ['required', 'uuid', 'distinct', Rule::exists('cash_accounts', 'id')->where('company_id', $companyId)->where('store_id', $storeId)->where('is_active', true)],
            'items.*.allocations.*.amount' => 'required|numeric|gt:0',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            foreach ($this->input('items', []) as $index => $item) {
                $hasCreditor = ! empty($item['creditor_id']);
                $allocations = $item['allocations'] ?? [];
                if ($hasCreditor && $allocations) {
                    $validator->errors()->add("items.$index.allocations", 'Expense utang tidak boleh memiliki sumber dana.');
                } elseif (! $hasCreditor && ! $allocations) {
                    $validator->errors()->add("items.$index.allocations", 'Expense dibayar wajib memiliki sumber dana.');
                } elseif (! $hasCreditor && abs(array_sum(array_column($allocations, 'amount')) - (float) ($item['amount'] ?? 0)) > 0.001) {
                    $validator->errors()->add("items.$index.allocations", 'Total sumber dana harus sama dengan nominal expense.');
                }
            }
        }];
    }
}
