<?php

use App\Models\Division;
use App\Models\Tournament;
use App\Models\TournamentRegistration;

describe('Division Model', function () {
    it('can be created via factory', function () {
        $d = Division::factory()->create();
        expect($d->exists())->toBeTrue();
    });

    it('belongs to tournament', function () {
        $t = Tournament::factory()->create();
        $d = Division::factory()->forTournament($t)->singles()->create();

        expect($d->tournament->is($t))->toBeTrue();
    });

    it('spots_available returns max of zero or spots_left', function () {
        $d = Division::factory()->singles()->create(['spots_left' => 5]);
        expect($d->spots_available)->toBe(5);

        $d2 = Division::factory()->singles()->create(['spots_left' => 0]);
        expect($d2->spots_available)->toBe(0);
    });

    it('is_sold_out true when spots left zero', function () {
        $d = Division::factory()->singles()->create(['spots_left' => 0]);
        expect($d->is_sold_out)->toBeTrue();

        $d2 = Division::factory()->singles()->create(['spots_left' => 5]);
        expect($d2->is_sold_out)->toBeFalse();
    });

    it('category_label returns ucfirst', function () {
        $d = Division::factory()->singles()->create();
        expect($d->category_label)->toBe('Singles');

        $d2 = Division::factory()->doubles()->create();
        expect($d2->category_label)->toBe('Doubles');
    });

    it('open scope returns divisions with spots left and open tournament', function () {
        $t1 = Tournament::factory()->create(['status' => 'open']);
        $t2 = Tournament::factory()->create(['status' => 'closed']);

        Division::factory()->forTournament($t1)->singles()->create(['spots_left' => 5]);
        Division::factory()->forTournament($t1)->doubles()->create(['spots_left' => 0]);
        Division::factory()->forTournament($t2)->singles()->create(['spots_left' => 5]);

        expect(Division::open()->count())->toBe(1);
    });

    it('entry_count calculates from draw_size minus spots_left', function () {
        $d = Division::factory()->singles()->create(['draw_size' => 32, 'spots_left' => 20]);
        expect($d->entry_count)->toBe(12);

        $d2 = Division::factory()->singles()->create(['draw_size' => 32, 'spots_left' => 32]);
        expect($d2->entry_count)->toBe(0);
    });

    it('has many registrations', function () {
        $d = Division::factory()->singles()->create();
        TournamentRegistration::factory()->create(['division_id' => $d->id]);
        TournamentRegistration::factory()->create(['division_id' => $d->id]);

        expect($d->registrations->count())->toBe(2);
    });

    it('team division has team size fields', function () {
        $d = Division::factory()->team()->create(['min_team_size' => 2, 'max_team_size' => 4]);
        expect($d->min_team_size)->toBe(2);
        expect($d->max_team_size)->toBe(4);
    });
});

describe('Tournament - Divisions Relationship', function () {
    it('has many divisions ordered by sort_order', function () {
        $t = Tournament::factory()->create();
        Division::factory()->forTournament($t)->singles()->create(['sort_order' => 2, 'name' => 'Singles']);
        Division::factory()->forTournament($t)->doubles()->create(['sort_order' => 0, 'name' => 'Doubles']);
        Division::factory()->forTournament($t)->team()->create(['sort_order' => 1, 'name' => 'Team']);

        expect($t->divisions[0]->name)->toBe('Doubles');
        expect($t->divisions[1]->name)->toBe('Team');
        expect($t->divisions[2]->name)->toBe('Singles');
    });
});
