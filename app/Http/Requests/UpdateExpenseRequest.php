<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expense_category_id' => 'sometimes|required|integer|exists:expense_categories,id',
            'date' => 'sometimes|required|date',
            'amount' => 'sometimes|required|numeric|min:0',
            'reference' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'expense_category_id.required' => 'Expense category is required',
            'expense_category_id.integer' => 'Expense category must be an integer',
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

