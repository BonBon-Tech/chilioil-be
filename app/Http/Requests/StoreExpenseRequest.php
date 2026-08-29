<?php

namespace App\Http\Requests;

use App\Models\CashAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExpenseRequest extends FormRequest
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
            'expense_category_id' => ['required', 'uuid', Rule::exists('expense_categories', 'id')->where('company_id', $companyId)],
            'date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'reference' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'creditor_id' => ['nullable', 'uuid', Rule::exists('daily_report_creditors', 'id')->where('company_id', $companyId)->where('store_id', $storeId)->where('is_active', true)],
            'allocations' => 'nullable|array|max:20',
            'allocations.*.account_id' => ['required', 'uuid', 'distinct', Rule::exists('cash_accounts', 'id')->where('company_id', $companyId)->where('store_id', $storeId)->where('is_active', true)],
            'allocations.*.amount' => 'required|numeric|gt:0',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $initialized = CashAccount::where('company_id', $this->user()?->company_id)
                ->where('store_id', $this->input('store_id'))->whereNotNull('opened_on')->exists();
            if (! $initialized) {
                return;
            }
            $allocations = $this->input('allocations', []);
            if ($this->filled('creditor_id') && $allocations) {
                $validator->errors()->add('allocations', 'Expense utang tidak boleh memiliki sumber dana.');
            } elseif (! $this->filled('creditor_id') && ! $allocations) {
                $validator->errors()->add('allocations', 'Expense dibayar wajib memiliki sumber dana.');
            } elseif (! $this->filled('creditor_id') && abs(array_sum(array_column($allocations, 'amount')) - (float) $this->input('amount')) > 0.001) {
                $validator->errors()->add('allocations', 'Total sumber dana harus sama dengan nominal expense.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'expense_category_id.required' => 'Expense category is required',
            'expense_category_id.exists' => 'Selected expense category does not exist',
            'date.required' => 'Date is required',
            'date.date' => 'Date must be a valid date',
            'amount.required' => 'Amount is required',
            'amount.numeric' => 'Amount must be a number',
            'amount.min' => 'Amount must be greater than or equal to 0',
            'reference.string' => 'Reference must be a string',
            'reference.max' => 'Reference cannot exceed 255 characters',
            'description.string' => 'Description must be a string',
            'description.max' => 'Description cannot exceed 1000 characters',
        ];
    }
}
