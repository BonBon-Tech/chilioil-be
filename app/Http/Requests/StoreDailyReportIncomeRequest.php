<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDailyReportIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'store_id' => ['required', 'uuid', Rule::exists('stores', 'id')->where('company_id', $companyId)],
            'amounts' => 'required|array',
            'amounts.shopeefood' => 'required|numeric|min:0',
            'amounts.grabfood' => 'required|numeric|min:0',
            'amounts.gofood' => 'required|numeric|min:0',
            'amounts.qris' => 'required|numeric|min:0',
            'amounts.cash' => 'required|numeric|min:0',
        ];
    }
}
