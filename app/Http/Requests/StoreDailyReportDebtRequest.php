<?php

namespace App\Http\Requests;

use App\Models\CashAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'payments.*.account_id' => ['nullable', 'uuid', Rule::exists('cash_accounts', 'id')
                ->where('company_id', $companyId)->where('store_id', $storeId)->where('is_active', true)],
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.note' => 'nullable|string|max:500',
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
            foreach ($this->input('payments', []) as $index => $payment) {
                if ((float) ($payment['amount'] ?? 0) > 0 && empty($payment['account_id'])) {
                    $validator->errors()->add("payments.$index.account_id", 'Akun sumber pembayaran wajib dipilih.');
                }
            }
        }];
    }
}
