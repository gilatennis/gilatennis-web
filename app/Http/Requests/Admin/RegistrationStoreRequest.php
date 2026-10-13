<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesRegistration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RegistrationStoreRequest extends FormRequest
{
    use ValidatesRegistration;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $division = $this->resolveRegistrationDivision();
        $isTeam = $division !== null && $division->participation_category === 'team';

        $rules = [
            'division_id' => ['required', 'exists:divisions,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'team_name' => [$isTeam ? 'required' : 'nullable', 'string', 'max:255'],
            'players' => ['required', 'array'],
            'payment_action' => ['required', 'in:pending,paid'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ];

        if ($division) {
            $rules = array_merge($rules, $this->playerRules($division, $isTeam));
        }

        return $rules;
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $division = $this->resolveRegistrationDivision();

                if (! $division && $this->filled('division_id')) {
                    $validator->errors()->add('division_id', 'The selected division does not belong to this tournament.');
                }

                $this->validatePlayerComposition($validator, $division);
            },
        ];
    }
}
