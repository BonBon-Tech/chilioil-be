<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:50|unique:products,code,' . $id,
            'store_id' => 'sometimes|required|string|exists:stores,id',
            'product_category_id' => 'sometimes|required|string|exists:product_categories,id',
            'selling_type' => 'sometimes|required|in:Sale,Purchase',
            'image_path' => 'nullable|string|max:500',
            'price' => 'sometimes|required|numeric|min:0',
            'status' => 'sometimes|boolean',
        ];
    }
}
