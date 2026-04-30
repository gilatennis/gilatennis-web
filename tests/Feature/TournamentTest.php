<?php

use App\Models\Tournament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('Tournament Model', function () {
    it('can be created via factory', function () {
        $t = Tournament::factory()->create();
        expect($t->exists())->toBeTrue();
    });

    it('creates with all fillable attributes', function () {
        $t = Tournament::factory()->create([
            'name' => 'Test Open',
            'city' => 'San Jose, CA',
            'surface' => 'hard',
            'fee' => 4500,
        ]);

        expect($t->name)->toBe('Test Open');
        expect($t->city)->toBe('San Jose, CA');
        expect($t->fee)->toBe(4500.0);
    });

    it('fee is stored and retrieved as a float', function () {
        $t = Tournament::factory()->create(['fee' => 600000]);
        expect($t->fee)->toBe(600000.0);
    });

    it('spots_available returns max of zero or spots_left', function () {
        $t = Tournament::factory()->create(['spots_left' => 5]);
        expect($t->spots_available)->toBe(5);

        $t2 = Tournament::factory()->create(['spots_left' => 0]);
        expect($t2->spots_available)->toBe(0);
    });

    it('is_sold_out is true when spots_left is zero', function () {
        $t = Tournament::factory()->create(['spots_left' => 0]);
        expect($t->is_sold_out)->toBeTrue();

        $t2 = Tournament::factory()->create(['spots_left' => 5]);
        expect($t2->is_sold_out)->toBeFalse();
    });

    it('surface_label returns human-readable label', function () {
        $hard = Tournament::factory()->create(['surface' => 'hard']);
        expect($hard->surface_label)->toBe('Hard');

        $clay = Tournament::factory()->create(['surface' => 'clay']);
        expect($clay->surface_label)->toBe('Clay');

        $indoor = Tournament::factory()->create(['surface' => 'indoor_hard']);
        expect($indoor->surface_label)->toBe('Indoor');
    });

    it('featured scope returns only featured tournaments', function () {
        Tournament::factory()->create(['is_featured' => true]);
        Tournament::factory()->create(['is_featured' => false]);
        Tournament::factory()->create(['is_featured' => true]);

        expect(Tournament::featured()->count())->toBe(2);
    });

    it('open scope returns open tournaments with spots left', function () {
        Tournament::factory()->create(['status' => 'open', 'spots_left' => 5]);
        Tournament::factory()->create(['status' => 'open', 'spots_left' => 0]);
        Tournament::factory()->create(['status' => 'closed', 'spots_left' => 5]);

        expect(Tournament::open()->count())->toBe(1);
    });

    it('prizes relationship returns ordered prizes', function () {
        $t = Tournament::factory()->create();
        $t->prizes()->create(['place' => 'Finalist', 'value' => '$200', 'sort_order' => 2]);
        $t->prizes()->create(['place' => 'Champion', 'value' => '$600', 'sort_order' => 0]);
        $t->prizes()->create(['place' => 'Semifinalist', 'value' => '$100', 'sort_order' => 1]);

        $prizes = $t->prizes;
        expect($prizes[0]->place)->toBe('Champion');
        expect($prizes[1]->place)->toBe('Semifinalist');
        expect($prizes[2]->place)->toBe('Finalist');
    });

    it('schedule_days relationship returns ordered days', function () {
        $t = Tournament::factory()->create();
        $day2 = $t->scheduleDays()->create(['day_label' => 'Sat', 'sort_order' => 1]);
        $day1 = $t->scheduleDays()->create(['day_label' => 'Fri', 'sort_order' => 0]);

        expect($t->scheduleDays[0]->day_label)->toBe('Fri');
        expect($t->scheduleDays[1]->day_label)->toBe('Sat');
    });

    it('rules relationship returns ordered rules', function () {
        $t = Tournament::factory()->create();
        $t->rules()->create(['body' => 'No phones.', 'sort_order' => 1]);
        $t->rules()->create(['body' => 'Be on time.', 'sort_order' => 0]);

        expect($t->rules[0]->body)->toBe('Be on time.');
        expect($t->rules[1]->body)->toBe('No phones.');
    });
});

describe('TournamentPrize Model', function () {
    it('belongs to tournament', function () {
        $t = Tournament::factory()->create();
        $prize = $t->prizes()->create(['place' => 'Champion', 'value' => '$600']);

        expect($prize->tournament->is($t))->toBeTrue();
    });
});

describe('TournamentScheduleDay Model', function () {
    it('belongs to tournament', function () {
        $t = Tournament::factory()->create();
        $day = $t->scheduleDays()->create(['day_label' => 'Fri']);

        expect($day->tournament->is($t))->toBeTrue();
    });

    it('has many items', function () {
        $t = Tournament::factory()->create();
        $day = $t->scheduleDays()->create(['day_label' => 'Fri']);
        $day->items()->create(['label' => 'R32 8:00 AM', 'sort_order' => 0]);
        $day->items()->create(['label' => 'QF 2:00 PM', 'sort_order' => 1]);

        expect($day->items->count())->toBe(2);
    });
});

describe('TournamentScheduleItem Model', function () {
    it('belongs to schedule day', function () {
        $t = Tournament::factory()->create();
        $day = $t->scheduleDays()->create(['day_label' => 'Fri']);
        $item = $day->items()->create(['label' => 'Final 3:00 PM']);

        expect($item->scheduleDay->is($day))->toBeTrue();
    });
});

describe('TournamentRule Model', function () {
    it('belongs to tournament', function () {
        $t = Tournament::factory()->create();
        $rule = $t->rules()->create(['body' => 'No coaching.']);

        expect($rule->tournament->is($t))->toBeTrue();
    });
});
