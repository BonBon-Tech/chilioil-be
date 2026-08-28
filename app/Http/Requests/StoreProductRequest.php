<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:products,code',
            'store_id' => [
                'required',
                'string',
                Rule::exists('stores', 'id')->where(fn($query) => $query
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')),
            ],
            'product_category_id' => [
                'required',
                'string',
                Rule::exists('product_categories', 'id')->where(fn($query) => $query
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')),
            ],
            'selling_type' => 'required|in:Sale,Purchase',
            'image_path' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'status' => 'sometimes|boolean',
        ];
    }
}
