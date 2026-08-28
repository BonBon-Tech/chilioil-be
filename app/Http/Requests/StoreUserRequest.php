<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $companyId = $this->user()?->company_id;

        return [
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string',
            'role_id' => [
                'required',
                Rule::exists('roles', 'id')->where(fn($query) => $query->where('name', '!=', 'owner')),
            ],
            'store_id' => [
                'nullable',
                Rule::exists('stores', 'id')->where(fn($query) => $query
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')),
            ],
        ];
    }
}
