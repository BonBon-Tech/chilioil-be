<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');
        $companyId = $this->user()?->company_id;

        return [
            'name' => 'sometimes|string',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'password' => 'sometimes|string',
            'role_id' => [
                'sometimes',
                Rule::exists('roles', 'id')->where(fn($query) => $query->where('name', '!=', 'owner')),
            ],
            'store_id' => [
                'sometimes',
                'nullable',
                Rule::exists('stores', 'id')->where(fn($query) => $query
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')),
            ],
        ];
    }
}
