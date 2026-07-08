<?php

use App\Models\Role;
use App\Models\Tournament;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
    $this->admin->roles()->syncWithoutDetaching([$role->id]);
});

function contactTournamentPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Contact Person Tournament',
        'slug' => 'contact-person-tournament',
        'dates' => 'July 10-12, 2026',
        'start' => '2026-07-10',
        'end' => '2026-07-12',
        'city' => 'San Jose, CA',
        'surface' => 'hard',
        'status' => 'open',
        'is_featured' => false,
        'perks' => [],
        'divisions' => [],
        'schedule' => [],
        'rules' => [],
    ], $overrides);
}

it('admin can save contact_person_name and contact_person_phone on create', function () {
    $data = contactTournamentPayload([
        'contact_person_name' => 'Budi Santoso',
        'contact_person_phone' => '+6287887240444',
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.tournaments.store'), $data)
        ->assertRedirect(route('admin.tournaments.index'));

    $tournament = Tournament::where('slug', 'contact-person-tournament')->firstOrFail();
    expect($tournament->contact_person_name)->toBe('Budi Santoso');
    expect($tournament->contact_person_phone)->toBe('+6287887240444');
});

it('admin can update contact_person_name and contact_person_phone', function () {
    $tournament = Tournament::factory()->create(['slug' => 'update-contact']);

    $data = contactTournamentPayload([
        'slug' => 'update-contact',
        'name' => $tournament->name,
        'contact_person_name' => 'Siti Aminah',
        'contact_person_phone' => '+628123456789',
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.tournaments.update', $tournament), $data)
        ->assertRedirect(route('admin.tournaments.show', $tournament));

    $tournament->refresh();
    expect($tournament->contact_person_name)->toBe('Siti Aminah');
    expect($tournament->contact_person_phone)->toBe('+628123456789');
});

it('rejects contact_person_name longer than 255 characters', function () {
    $data = contactTournamentPayload([
        'slug' => 'long-name',
        'contact_person_name' => str_repeat('a', 256),
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.tournaments.store'), $data)
        ->assertSessionHasErrors('contact_person_name');

    expect(Tournament::where('slug', 'long-name')->exists())->toBeFalse();
});

it('rejects contact_person_phone longer than 20 characters', function () {
    $data = contactTournamentPayload([
        'slug' => 'long-phone',
        'contact_person_phone' => str_repeat('1', 21),
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.tournaments.store'), $data)
        ->assertSessionHasErrors('contact_person_phone');

    expect(Tournament::where('slug', 'long-phone')->exists())->toBeFalse();
});

it('both fields are optional and save as null when omitted', function () {
    $data = contactTournamentPayload(['slug' => 'no-contact']);

    $this->actingAs($this->admin)
        ->post(route('admin.tournaments.store'), $data)
        ->assertRedirect(route('admin.tournaments.index'));

    $tournament = Tournament::where('slug', 'no-contact')->firstOrFail();
    expect($tournament->contact_person_name)->toBeNull();
    expect($tournament->contact_person_phone)->toBeNull();
});

it('exposes contact_person_name and contact_person_phone on the public tournament show page', function () {
    $tournament = Tournament::factory()->create([
        'slug' => 'public-contact-show',
        'contact_person_name' => 'Public Name',
        'contact_person_phone' => '+6287887240444',
    ]);

    $this->get(route('tournaments.show', $tournament))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tournaments/Show')
            ->where('tournament.contact_person_name', 'Public Name')
            ->where('tournament.contact_person_phone', '+6287887240444')
        );
});

it('exposes contact_person_name and contact_person_phone on the registration page', function () {
    $tournament = Tournament::factory()
        ->withContact('Reg Name', '+628999888777')
        ->create(['slug' => 'public-contact-register']);

    $tournament->divisions()->create([
        'name' => 'Open Singles',
        'slug' => 'open-singles',
        'participation_category' => 'singles',
        'draw_size' => 12,
        'spots_available' => 10,
        'fee' => 100000,
        'sort_order' => 0,
    ]);

    $this->get(route('tournaments.register', $tournament))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tournaments/Register')
            ->where('tournament.contact_person_name', 'Reg Name')
            ->where('tournament.contact_person_phone', '+628999888777')
        );
});
