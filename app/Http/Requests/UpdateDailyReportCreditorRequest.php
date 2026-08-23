<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDailyReportCreditorRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100', Rule::unique('daily_report_creditors', 'name')
                ->ignore($this->route('creditor'))->where('company_id', $companyId)->where('store_id', $storeId)],
            'opening_balance' => 'required|numeric|min:0',
            'opening_date' => 'required|date|before_or_equal:today',
            'is_active' => 'required|boolean',
        ];
    }
}
