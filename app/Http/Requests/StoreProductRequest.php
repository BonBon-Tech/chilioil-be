<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:products,code',
            'store_id' => 'required|integer|exists:stores,id',
            'product_category_id' => 'required|integer|exists:product_categories,id',
            'selling_type' => 'required|in:Ingredient,Sale,Employee',
            'image_path' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'status' => 'sometimes|boolean',
        ];
    }
}
