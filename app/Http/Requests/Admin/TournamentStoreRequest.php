<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\TournamentRules;
use Illuminate\Foundation\Http\FormRequest;

class TournamentStoreRequest extends FormRequest
{
    use TournamentRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge($this->baseRules(), [
            'slug' => ['required', 'string', 'max:255', 'unique:tournaments,slug'],
        ]);
    }

    public function withValidator($validator): void
    {
        $this->validateLevelRequirementKeys($validator);
        $this->validatePlayerFieldRequirements($validator);
    }
}
