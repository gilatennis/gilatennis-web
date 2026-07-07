<?php

use App\DTOs\DuitkuInvoiceResponse;
use App\Models\Division;
use App\Models\Tournament;
use App\Services\DuitkuService;
use Illuminate\Support\Facades\Vite;

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

function fakeBrowserInvoiceService(): void
{
    test()->swap(DuitkuService::class, new class extends DuitkuService
    {
        public function __construct() {}

        public function createInvoice($registration): DuitkuInvoiceResponse
        {
            return new DuitkuInvoiceResponse(
                merchantCode: 'DEMO0001',
                reference: 'DK-TESTREF123',
                paymentUrl: 'https://sandbox.duitku.com/pay/DEMO123',
                statusCode: '00',
                statusMessage: 'Invoice created',
            );
        }
    });
}

beforeEach(fn () => Vite::useHotFile(sys_get_temp_dir().'/vite-hot-file-disabled'));

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

    it('successfully registers a complete team through the UI', function () {
        fakeBrowserInvoiceService();

        $div = browserTeamDivision([
            'draw_size' => 8,
            'fee' => 250000,
            'min_team_size' => 6,
            'max_team_size' => 8,
            'min_female_players' => 1,
            'min_male_players' => 4,
            'level_requirements' => ['Upper Beginner' => 4, 'Intermediate' => 2],
        ]);
        $t = $div->tournament;

        $page = visit(route('tournaments.register', $t->slug));

        $page->fill('team_name', 'E2E Success')
            ->click('Next');

        $page->fill('name', 'Captain E2E')
            ->fill('email', 'captain@e2e.test')
            ->fill('phone', '0811111111');

        $page->click('#gender');
        $page->click('[data-value="female"]');

        $page->click('#level');
        $page->click('[data-value="Intermediate"]');

        $roster = [
            ['Alice Player', 'alice@e2e.test', '0822222222', 'male', 'Upper Beginner'],
            ['Bob Player', 'bob@e2e.test', '0833333333', 'male', 'Upper Beginner'],
            ['Charlie Player', 'charlie@e2e.test', '0844444444', 'male', 'Upper Beginner'],
            ['Diana Player', 'diana@e2e.test', '0855555555', 'male', 'Upper Beginner'],
            ['Eve Player', 'eve@e2e.test', '0866666666', 'male', 'Intermediate'],
        ];

        foreach ($roster as $i) {
            $page->click('Add player');
        }

        foreach ($roster as $idx => [$name, $email, $phone, $gender, $level]) {
            $n = $idx + 1;
            $page->fill("#player-{$n}-name", $name)
                ->fill("#player-{$n}-email", $email)
                ->fill("#player-{$n}-phone", $phone)
                ->click("#player-{$n}-gender");
            $page->click("[data-value=\"{$gender}\"]");
            $page->click("#player-{$n}-level");
            $page->click("[data-value=\"{$level}\"]");
        }

        $page->click('Review order')
            ->assertSee('Review & pay');

        $page->check('waiver_accepted')
            ->click('Proceed to Payment')
            ->assertUrlIs('https://sandbox.duitku.com/pay/DEMO123');
    });
});
