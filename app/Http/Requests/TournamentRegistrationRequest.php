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
        $division = $this->route('tournament')?->divisions()
            ->where('id', $this->input('division_id'))
            ->first();

        $isTeam = $division && $division->participation_category === 'team';
        $isDoubles = $division && $division->participation_category === 'doubles';

        return [
            'division_id' => ['required', 'exists:divisions,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'team_name' => [$isTeam ? 'required' : 'nullable', 'string', 'max:255'],
            'tshirt_size' => ['nullable', 'in:XS,S,M,L,XL,XXL'],
            'dietary' => ['nullable', 'string', 'max:500'],
            'waiver_accepted' => ['required', 'accepted'],
            'players' => ['required', 'array'],
            'players.*.name' => ['required', 'string', 'max:255'],
            'players.*.email' => ['required', 'email', 'max:255'],
            'players.*.phone' => ['required', 'string', 'max:50'],
            'players.*.date_of_birth' => ['required', 'date', 'before:today'],
            'players.*.rating_type' => ['nullable', 'in:UTR,ITN,USTA'],
            'players.*.rating_value' => ['nullable', 'numeric'],
            'players.*.tshirt_size' => ['nullable', 'in:XS,S,M,L,XL,XXL'],
            'players.*.dietary' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $division = $this->route('tournament')?->divisions()
                ->where('id', $this->input('division_id'))
                ->first();

            if (! $division) {
                return;
            }

            $playerCount = count($this->input('players', []));

            if ($division->participation_category === 'singles' && $playerCount !== 1) {
                $validator->errors()->add('players', 'Singles division requires exactly 1 player.');
            }

            if ($division->participation_category === 'doubles' && $playerCount !== 2) {
                $validator->errors()->add('players', 'Doubles division requires exactly 2 players.');
            }

            if ($division->participation_category === 'team') {
                $min = $division->min_team_size;
                $max = $division->max_team_size;

                if ($min && $playerCount < $min) {
                    $validator->errors()->add('players', "Team requires at least {$min} players.");
                }

                if ($max && $playerCount > $max) {
                    $validator->errors()->add('players', "Team allows at most {$max} players.");
                }
            }

            if ($division->hasRatingConstraints()) {
                foreach ($this->input('players', []) as $i => $player) {
                    $val = $player['rating_value'] ?? null;

                    if ($val === null || $val === '') {
                        continue;
                    }

                    $numVal = (float) $val;

                    if ($numVal < (float) $division->rating_min || $numVal > (float) $division->rating_max) {
                        $validator->errors()->add(
                            "players.{$i}.rating_value",
                            "Rating must be between {$division->rating_min} and {$division->rating_max} for {$division->rating_type}."
                        );
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'players.*.email.email' => 'Each player must have a valid email address.',
        ];
    }
}
