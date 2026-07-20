<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'contact_wa_number' => ['nullable', 'string', 'max:20'],
            'transaction_fee' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
