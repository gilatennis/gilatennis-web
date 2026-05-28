<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'contact_wa_number' => ['required', 'string', 'max:20'],
        ];
    }
}
