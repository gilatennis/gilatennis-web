<?php

use App\Models\Role;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
    $this->admin->roles()->syncWithoutDetaching([$role->id]);
});

function tournamentPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Cover Image Tournament',
        'slug' => 'cover-image-tournament',
        'dates' => 'July 10-12, 2026',
        'start' => '2026-06-27',
        'end' => '2026-06-28',
        'city' => 'San Jose, CA',
        'surface' => 'hard',
        'status' => 'draft',
        'is_featured' => false,
        'perks' => [],
        'divisions' => [],
        'schedule' => [],
        'rules' => [],
    ], $overrides);
}

it('admin can upload a cover image when creating a tournament', function () {
    $cover = UploadedFile::fake()->image('cover.jpg', 1600, 900);

    $data = tournamentPayload();
    $data['cover_image'] = $cover;

    $this->actingAs($this->admin)
        ->post(route('admin.tournaments.store'), $data)
        ->assertRedirect(route('admin.tournaments.index'));

    $tournament = Tournament::where('slug', 'cover-image-tournament')->firstOrFail();
    expect($tournament->cover_image)->not->toBeNull();
    expect($tournament->cover_image)->toStartWith('tournament-covers/');
    Storage::disk('public')->assertExists($tournament->cover_image);
});

it('admin can replace an existing cover image', function () {
    $tournament = Tournament::factory()->create(['slug' => 'replace-cover']);
    $original = UploadedFile::fake()->image('original.jpg', 1600, 900);
    $originalPath = $original->store('tournament-covers', 'public');
    $tournament->update(['cover_image' => $originalPath]);

    $replacement = UploadedFile::fake()->image('replacement.png', 1600, 900);
    $data = tournamentPayload(['slug' => 'replace-cover', 'name' => $tournament->name]);
    $data['cover_image'] = $replacement;

    $this->actingAs($this->admin)
        ->put(route('admin.tournaments.update', $tournament), $data)
        ->assertRedirect(route('admin.tournaments.show', $tournament));

    Storage::disk('public')->assertMissing($originalPath);
    $tournament->refresh();
    expect($tournament->cover_image)->not->toBe($originalPath);
    expect($tournament->cover_image)->toEndWith('.png');
    Storage::disk('public')->assertExists($tournament->cover_image);
});

it('admin can remove the cover image via the remove flag', function () {
    $tournament = Tournament::factory()->create(['slug' => 'remove-cover']);
    $cover = UploadedFile::fake()->image('cover.jpg', 1600, 900);
    $coverPath = $cover->store('tournament-covers', 'public');
    $tournament->update(['cover_image' => $coverPath]);
    Storage::disk('public')->assertExists($coverPath);

    $data = tournamentPayload(['slug' => 'remove-cover', 'name' => $tournament->name]);
    $data['cover_image_remove'] = true;

    $this->actingAs($this->admin)
        ->put(route('admin.tournaments.update', $tournament), $data)
        ->assertRedirect(route('admin.tournaments.show', $tournament));

    Storage::disk('public')->assertMissing($coverPath);
    $tournament->refresh();
    expect($tournament->cover_image)->toBeNull();
});

it('rejects an upload with an invalid MIME type', function () {
    $notAnImage = UploadedFile::fake()->create('cover.txt', 100, 'text/plain');

    $data = tournamentPayload(['slug' => 'bad-mime']);
    $data['cover_image'] = $notAnImage;

    $this->actingAs($this->admin)
        ->post(route('admin.tournaments.store'), $data)
        ->assertSessionHasErrors('cover_image');

    expect(Tournament::where('slug', 'bad-mime')->exists())->toBeFalse();
});

it('rejects an upload larger than 5 MB', function () {
    $oversized = UploadedFile::fake()->image('huge.jpg', 1600, 900)->size(6000);

    $data = tournamentPayload(['slug' => 'too-large']);
    $data['cover_image'] = $oversized;

    $this->actingAs($this->admin)
        ->post(route('admin.tournaments.store'), $data)
        ->assertSessionHasErrors('cover_image');

    expect(Tournament::where('slug', 'too-large')->exists())->toBeFalse();
});

it('deletes the cover image from storage when the tournament is deleted', function () {
    $tournament = Tournament::factory()->create();
    $cover = UploadedFile::fake()->image('cover.jpg', 1600, 900);
    $coverPath = $cover->store('tournament-covers', 'public');
    $tournament->update(['cover_image' => $coverPath]);

    Storage::disk('public')->assertExists($coverPath);

    $this->actingAs($this->admin)
        ->delete(route('admin.tournaments.destroy', $tournament))
        ->assertRedirect(route('admin.tournaments.index'));

    Storage::disk('public')->assertMissing($coverPath);
});

it('exposes the cover image URL on the public tournament show page', function () {
    $tournament = Tournament::factory()->create(['slug' => 'public-cover']);
    $cover = UploadedFile::fake()->image('cover.jpg', 1600, 900);
    $coverPath = $cover->store('tournament-covers', 'public');
    $tournament->update(['cover_image' => $coverPath]);

    $this->get(route('tournaments.show', $tournament))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tournaments/Show')
            ->where('tournament.cover_image_url', Storage::disk('public')->url($coverPath))
        );
});
