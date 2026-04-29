<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TournamentRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'rating_type' => ['required', 'in:UTR,ITN,USTA'],
            'rating_value' => ['required', 'numeric', 'min:1', 'max:16.5'],
            'event_type' => ['required', 'in:singles,doubles,both'],
            'find_partner' => ['boolean'],
            'partner_name' => ['nullable', 'string'],
            'partner_email' => ['nullable', 'email'],
            'tshirt_size' => ['required', 'in:XS,S,M,L,XL,XXL'],
            'dietary' => ['nullable', 'string', 'max:500'],
            'waiver_accepted' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'partner_email.email' => 'Partner email must be a valid email address.',
        ];
    }
}
