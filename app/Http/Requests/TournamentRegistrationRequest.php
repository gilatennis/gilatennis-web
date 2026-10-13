<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesRegistration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TournamentRegistrationRequest extends FormRequest
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
            'waiver_accepted' => ['required', 'accepted'],
            'players' => ['required', 'array'],
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
                $this->validatePlayerComposition($validator, $this->resolveRegistrationDivision());
            },
        ];
    }

    public function messages(): array
    {
        return [
            'players.*.email.email' => 'Each player must have a valid email address.',
        ];
    }
}
