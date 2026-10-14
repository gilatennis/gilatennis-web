<?php

use App\Models\Division;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Carbon\CarbonImmutable;

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
            'ball_type' => 'Penn Championship',
        ]);

        expect($t->name)->toBe('Test Open');
        expect($t->city)->toBe('San Jose, CA');
        expect($t->ball_type)->toBe('Penn Championship');
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

    it('open scope returns open tournaments with available divisions', function () {
        $t1 = Tournament::factory()->create(['status' => 'open']);
        Division::factory()->forTournament($t1)->singles()->create(['draw_size' => 32, 'fee' => 50000]);

        $t2 = Tournament::factory()->create(['status' => 'open']);
        $div2 = Division::factory()->forTournament($t2)->singles()->create(['draw_size' => 2, 'fee' => 50000]);
        TournamentRegistration::factory()->forTournament($t2)->create(['division_id' => $div2->id, 'payment_status' => 'paid']);
        TournamentRegistration::factory()->forTournament($t2)->create(['division_id' => $div2->id, 'payment_status' => 'paid']);

        Tournament::factory()->create(['status' => 'closed']);

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

    it('status_label returns Canceled for canceled tournament', function () {
        $t = Tournament::factory()->canceled()->create();
        expect($t->status_label)->toBe('Canceled');
    });

    it('casts start and end columns to dates', function () {
        $t = Tournament::factory()->create([
            'start' => '2026-09-01',
            'end' => '2026-09-03',
        ]);

        expect($t->start)->toBeInstanceOf(CarbonImmutable::class);
        expect($t->end)->toBeInstanceOf(CarbonImmutable::class);
        expect($t->start->format('Y-m-d'))->toBe('2026-09-01');
    });

    it('visibleOnPublicSite scope hides canceled tournaments whose end date has passed', function () {
        Tournament::factory()->canceled()->create(['end' => now()->subDay()->toDateString()]);

        expect(Tournament::visibleOnPublicSite()->count())->toBe(0);
    });

    it('visibleOnPublicSite scope keeps canceled tournaments whose end date is in the future', function () {
        Tournament::factory()->canceled()->create(['end' => now()->addWeek()->toDateString()]);

        expect(Tournament::visibleOnPublicSite()->count())->toBe(1);
    });

    it('visibleOnPublicSite scope keeps open tournaments regardless of date', function () {
        Tournament::factory()->create(['status' => 'open', 'end' => now()->subDay()->toDateString()]);
        Tournament::factory()->create(['status' => 'open', 'end' => now()->addWeek()->toDateString()]);

        expect(Tournament::visibleOnPublicSite()->count())->toBe(2);
    });

    it('visibleOnPublicSite scope excludes draft, closed, completed', function () {
        Tournament::factory()->create(['status' => 'draft']);
        Tournament::factory()->create(['status' => 'closed']);
        Tournament::factory()->create(['status' => 'completed']);

        expect(Tournament::visibleOnPublicSite()->count())->toBe(0);
    });

    it('visibleOnPublicSite scope excludes canceled tournament with null end date', function () {
        Tournament::factory()->canceled()->create(['end' => null]);

        expect(Tournament::visibleOnPublicSite()->count())->toBe(0);
    });

    it('isCanceled helper returns true only for canceled status', function () {
        expect(Tournament::factory()->canceled()->create()->isCanceled())->toBeTrue();
        expect(Tournament::factory()->create(['status' => 'open'])->isCanceled())->toBeFalse();
        expect(Tournament::factory()->create(['status' => 'completed'])->isCanceled())->toBeFalse();
    });

    it('isCanceledAndOver requires both canceled status and past end date', function () {
        $pastCanceled = Tournament::factory()->canceled()->create(['end' => now()->subDay()->toDateString()]);
        $futureCanceled = Tournament::factory()->canceled()->create(['end' => now()->addWeek()->toDateString()]);
        $openPast = Tournament::factory()->create(['status' => 'open', 'end' => now()->subDay()->toDateString()]);

        expect($pastCanceled->isCanceledAndOver())->toBeTrue();
        expect($futureCanceled->isCanceledAndOver())->toBeFalse();
        expect($openPast->isCanceledAndOver())->toBeFalse();
    });

    it('isPubliclyVisible returns true for open and canceled-not-over only', function () {
        $open = Tournament::factory()->create(['status' => 'open']);
        $canceledFuture = Tournament::factory()->canceled()->create(['end' => now()->addWeek()->toDateString()]);
        $canceledPast = Tournament::factory()->canceled()->create(['end' => now()->subDay()->toDateString()]);
        $canceledNoEnd = Tournament::factory()->canceled()->create(['end' => null]);
        $closed = Tournament::factory()->create(['status' => 'closed']);

        expect($open->isPubliclyVisible())->toBeTrue();
        expect($canceledFuture->isPubliclyVisible())->toBeTrue();
        expect($canceledPast->isPubliclyVisible())->toBeFalse();
        expect($canceledNoEnd->isPubliclyVisible())->toBeFalse();
        expect($closed->isPubliclyVisible())->toBeFalse();
    });

    it('isRegistrationOpen reflects status, deadline and spots', function () {
        $open = Tournament::factory()->create(['status' => 'open', 'registration_closes' => now()->addWeek()]);
        Division::factory()->forTournament($open)->singles()->create(['draw_size' => 32, 'fee' => 50000]);

        $pastDeadline = Tournament::factory()->registrationClosed()->create();
        Division::factory()->forTournament($pastDeadline)->singles()->create(['draw_size' => 32, 'fee' => 50000]);

        $noDeadline = Tournament::factory()->create(['status' => 'open', 'registration_closes' => null]);
        Division::factory()->forTournament($noDeadline)->singles()->create(['draw_size' => 32, 'fee' => 50000]);

        $notOpen = Tournament::factory()->create(['status' => 'closed', 'registration_closes' => now()->addWeek()]);
        Division::factory()->forTournament($notOpen)->singles()->create(['draw_size' => 32, 'fee' => 50000]);

        expect($open->acceptsRegistrations())->toBeTrue();
        expect($pastDeadline->acceptsRegistrations())->toBeFalse();
        expect($noDeadline->acceptsRegistrations())->toBeTrue();
        expect($notOpen->acceptsRegistrations())->toBeFalse();
    });

    it('registrationHasClosed is true only for a past deadline', function () {
        expect(Tournament::factory()->registrationClosed()->create()->registrationHasClosed())->toBeTrue();
        expect(Tournament::factory()->create(['registration_closes' => now()->addWeek()])->registrationHasClosed())->toBeFalse();
        expect(Tournament::factory()->create(['registration_closes' => null])->registrationHasClosed())->toBeFalse();
    });

    it('open scope excludes tournaments whose registration deadline has passed', function () {
        $t = Tournament::factory()->registrationClosed()->create();
        Division::factory()->forTournament($t)->singles()->create(['draw_size' => 32, 'fee' => 50000]);

        expect(Tournament::open()->count())->toBe(0);
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
