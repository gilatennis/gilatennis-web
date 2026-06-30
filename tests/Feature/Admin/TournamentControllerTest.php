<?php

use App\Models\Division;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\TournamentPrize;
use App\Models\TournamentRule;
use App\Models\TournamentScheduleDay;
use App\Models\TournamentScheduleItem;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
    $this->admin->roles()->syncWithoutDetaching([$role->id]);
});

describe('Admin Tournament CRUD', function () {
    it('admin can list tournaments', function () {
        Tournament::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.index'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tournaments/Index')
                ->has('tournaments.data', 3)
            );
    });

    it('admin can search tournaments by name', function () {
        Tournament::factory()->create(['name' => 'NorCal Spring Open']);
        Tournament::factory()->create(['name' => 'Red Clay Challenge']);
        Tournament::factory()->create(['name' => 'Sunset Classic']);

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.index', ['search' => 'Clay']))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('tournaments.data', 1)
            );
    });

    it('admin can view tournament detail', function () {
        $tournament = Tournament::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.show', $tournament))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tournaments/Detail')
                ->has('registrationStats')
            );
    });

    it('admin can view create form', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.create'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tournaments/Edit')
            );
    });

    it('admin can create tournament with nested data', function () {
        $data = [
            'name' => 'Summer Slam 2026',
            'slug' => 'summer-slam-2026',
            'dates' => 'July 10-12, 2026',
            'start' => '2026-06-27',
            'end' => '2026-06-28',
            'city' => 'San Jose, CA',
            'surface' => 'hard',
            'status' => 'draft',
            'is_featured' => true,
            'perks' => [['key' => 'Gift Bag', 'value' => 'Merch']],
            'divisions' => [
                [
                    'name' => "Men's Singles 3.5",
                    'slug' => 'mens-singles-3-5',
                    'participation_category' => 'singles',
                    'draw_size' => 16,
                    'fee' => 45000,
                    'format' => 'Single Elimination',
                    'gender' => 'mens',
                    'rating_type' => 'UTR',
                    'rating_min' => 3.0,
                    'rating_max' => 5.0,
                    'prizes' => [
                        ['place' => 'Champion', 'value' => 'Trophy + $500'],
                        ['place' => 'Finalist', 'value' => 'Medal'],
                    ],
                ],
            ],
            'schedule' => [
                [
                    'day_label' => 'Day 1 - Saturday',
                    'items' => [
                        ['label' => '8:00 AM - Check-in'],
                        ['label' => '9:00 AM - Round 1'],
                    ],
                ],
            ],
            'rules' => [
                ['body' => 'All matches follow USTA rules.'],
                ['body' => 'Players must check in 30 minutes before match.'],
            ],
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.tournaments.store'), $data)
            ->assertRedirect(route('admin.tournaments.index'));

        $tournament = Tournament::where('name', 'Summer Slam 2026')->first();
        expect($tournament)->not->toBeNull();
        expect($tournament->divisions)->toHaveCount(1);
        expect($tournament->scheduleDays)->toHaveCount(1);
        expect($tournament->rules)->toHaveCount(2);

        $division = $tournament->divisions->first();
        expect($division->prizes)->toHaveCount(2);
    });

    it('admin can view edit form', function () {
        $tournament = Tournament::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.tournaments.edit', $tournament))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tournaments/Edit')
            );
    });

    it('admin can update tournament and sync nested data', function () {
        $tournament = Tournament::factory()->create();
        $division = Division::factory()->create([
            'tournament_id' => $tournament->id,
            'name' => 'Old Division',
            'slug' => 'old-division',
            'draw_size' => 16,
            'fee' => 45000,
        ]);
        $oldPrize = TournamentPrize::create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
            'place' => 'Old Prize',
            'value' => 'Old Value',
            'sort_order' => 0,
        ]);
        $oldDay = TournamentScheduleDay::create([
            'tournament_id' => $tournament->id,
            'day_label' => 'Old Day',
            'sort_order' => 0,
        ]);
        $oldItem = TournamentScheduleItem::create([
            'schedule_day_id' => $oldDay->id,
            'label' => 'Old Item',
            'sort_order' => 0,
        ]);
        $oldRule = TournamentRule::create([
            'tournament_id' => $tournament->id,
            'body' => 'Old Rule',
            'sort_order' => 0,
        ]);

        $data = [
            'name' => 'Updated Tournament',
            'slug' => $tournament->slug,
            'dates' => $tournament->dates,
            'start' => $tournament->start,
            'end' => $tournament->end,
            'city' => $tournament->city,
            'surface' => 'clay',
            'status' => 'open',
            'is_featured' => false,
            'perks' => [],
            'divisions' => [
                [
                    'id' => $division->id,
                    'name' => 'Updated Division',
                    'slug' => 'updated-division',
                    'participation_category' => 'doubles',
                    'draw_size' => 32,
                    'fee' => 55000,
                    'format' => 'Double Elimination',
                    'gender' => 'mixed',
                    'prizes' => [
                        ['id' => $oldPrize->id, 'place' => 'Updated Prize', 'value' => 'Updated Value'],
                        ['place' => 'New Prize', 'value' => 'New Value'],
                    ],
                ],
            ],
            'schedule' => [
                [
                    'id' => $oldDay->id,
                    'day_label' => 'Updated Day',
                    'items' => [
                        ['id' => $oldItem->id, 'label' => 'Updated Item'],
                        ['label' => 'New Item'],
                    ],
                ],
            ],
            'rules' => [
                ['id' => $oldRule->id, 'body' => 'Updated Rule'],
                ['body' => 'New Rule'],
            ],
        ];

        $this->actingAs($this->admin)
            ->put(route('admin.tournaments.update', $tournament), $data)
            ->assertRedirect(route('admin.tournaments.show', $tournament));

        $tournament->refresh();
        expect($tournament->name)->toBe('Updated Tournament');
        expect($tournament->surface)->toBe('clay');
        expect($tournament->divisions)->toHaveCount(1);

        $division->refresh();
        expect($division->name)->toBe('Updated Division');
        expect($division->prizes)->toHaveCount(2);

        $oldPrize->refresh();
        expect($oldPrize->value)->toBe('Updated Value');

        expect($tournament->scheduleDays)->toHaveCount(1);
        $oldDay->refresh();
        expect($oldDay->items)->toHaveCount(2);

        expect($tournament->rules)->toHaveCount(2);
    });

    it('admin can delete tournament', function () {
        $tournament = Tournament::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.tournaments.destroy', $tournament))
            ->assertRedirect(route('admin.tournaments.index'));

        expect(Tournament::find($tournament->id))->toBeNull();
    });

    it('stored perks are empty array when empty', function () {
        $data = [
            'name' => 'No Perks Tournament',
            'slug' => 'no-perks',
            'dates' => 'Jan 1, 2026',
            'start' => '2026-06-27',
            'end' => '2026-06-28',
            'city' => 'Test City',
            'surface' => 'hard',
            'status' => 'draft',
            'perks' => [],
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.tournaments.store'), $data)
            ->assertRedirect();

        $tournament = Tournament::where('name', 'No Perks Tournament')->first();
        expect($tournament->perks)->toBe([]);
    });

    it('non-admin cannot access admin tournament routes', function () {
        $user = User::factory()->create();
        $tournament = Tournament::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.tournaments.index'))
            ->assertStatus(403);

        $this->actingAs($user)
            ->get(route('admin.tournaments.show', $tournament))
            ->assertStatus(403);
    });

    it('guest is redirected to admin login', function () {
        $tournament = Tournament::factory()->create();

        $this->get(route('admin.tournaments.index'))
            ->assertRedirect('/admin/login');
    });
});
