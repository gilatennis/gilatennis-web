<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesRegistration;
use App\Models\Division;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RegistrationUpdateRequest extends FormRequest
{
    use ValidatesRegistration;

    private ?Division $targetDivisionCache = null;

    private bool $targetDivisionResolved = false;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $division = $this->targetDivision();
        $isTeam = $division !== null && $division->participation_category === 'team';

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'team_name' => [$isTeam ? 'required' : 'nullable', 'string', 'max:255'],
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
                $this->validatePlayerComposition($validator, $this->targetDivision());
            },
        ];
    }

    private function targetDivision(): ?Division
    {
        if ($this->targetDivisionResolved) {
            return $this->targetDivisionCache;
        }

        $this->targetDivisionResolved = true;

        return $this->targetDivisionCache = $this->route('registration')?->division()->first();
    }
}
