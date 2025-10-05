<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWifiCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|unique:wifi_credentials,code,' . $this->route('wifi_credential'),
            'is_active' => 'required|boolean',
        ];
    }
}

