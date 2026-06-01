<?php

use App\Models\Division;
use App\Models\Tournament;

function browserSinglesDivision(array $divAttrs = []): Division
{
    return Division::factory()
        ->forTournament(Tournament::factory()->create(['status' => 'open']))
        ->singles()
        ->create(array_merge([
            'draw_size' => 32, 'fee' => 50000, 'sort_order' => 0, 'gender' => null,
        ], $divAttrs));
}

function browserTeamDivision(array $divAttrs = []): Division
{
    return Division::factory()
        ->forTournament(Tournament::factory()->create(['status' => 'open']))
        ->team()
        ->create(array_merge([
            'draw_size' => 8, 'fee' => 120000, 'min_team_size' => 2, 'max_team_size' => 4, 'sort_order' => 0, 'gender' => null,
        ], $divAttrs));
}

beforeEach(fn () => \Illuminate\Support\Facades\Vite::useHotFile(sys_get_temp_dir() . '/vite-hot-file-disabled'));

describe('registration form validation display', function () {
    it('shows inline gender validation error when captain gender is missing', function () {
        $div = browserSinglesDivision();
        $t = $div->tournament;

        $page = visit(route('tournaments.register', $t->slug));

        $page->click('Next')
            ->fill('name', 'John')
            ->fill('email', 'john@test.com')
            ->fill('phone', '0812000000')
            ->click('Review order')
            ->assertSee('Gender is required.');
    });

    it('shows inline field errors when required fields are empty', function () {
        $div = browserSinglesDivision();
        $t = $div->tournament;

        $page = visit(route('tournaments.register', $t->slug));

        $page->click('Next')
            ->click('Review order')
            ->assertSee('Name is required.')
            ->assertSee('Email is required.')
            ->assertSee('Phone is required.')
            ->assertSee('Gender is required.');
    });

    it('shows composition error banner when gender minimums not met on team division', function () {
        $div = browserTeamDivision([
            'min_female_players' => 1,
            'min_male_players' => 2,
        ]);
        $t = $div->tournament;

        $page = visit(route('tournaments.register', $t->slug));

        $page->fill('team_name', 'Ace Breakers')
            ->click('Next');

        $page->fill('name', 'John Captain')
            ->fill('email', 'john@test.com')
            ->fill('phone', '0812000000');

        $page->click('#gender');
        $page->click('[data-value="male"]');

        $page->click('Add player');
        $page->click('Add player');

        $page->fill('#player-1-name', 'Bob Player')
            ->fill('#player-1-email', 'bob@test.com')
            ->fill('#player-1-phone', '0813000000')
            ->click('#player-1-gender');
        $page->click('[data-value="male"]');

        $page->fill('#player-2-name', 'Charlie Player')
            ->fill('#player-2-email', 'charlie@test.com')
            ->fill('#player-2-phone', '0814000000')
            ->click('#player-2-gender');
        $page->click('[data-value="male"]');

        $page->click('Review order')
            ->assertSee('Some fields need your attention.')
            ->assertSee('Need at least 1 female player(s)');
    });
});
