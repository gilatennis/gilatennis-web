<?php

use App\Models\Article;
use App\Models\Court;
use App\Models\Tournament;

it('returns tournaments matching search query', function () {
    Tournament::factory()->create([
        'name' => 'Jakarta Open Championship',
        'city' => 'Jakarta',
        'status' => 'open',
    ]);

    Tournament::factory()->create([
        'name' => 'Bali Tennis Classic',
        'city' => 'Bali',
        'status' => 'open',
    ]);

    $response = $this->getJson('/search?q=Jakarta&type=tournaments');

    $response->assertStatus(200);
    $response->assertJsonPath('tournaments.0.title', 'Jakarta Open Championship');
});

it('returns articles matching search query', function () {
    Article::factory()->create([
        'title' => 'Best Tennis Rackets for Beginners',
        'kicker' => 'Gear Review',
        'author' => 'John Smith',
        'is_published' => true,
    ]);

    Article::factory()->create([
        'title' => 'Advanced Footwork Techniques',
        'kicker' => 'Training Tips',
        'author' => 'Jane Doe',
        'is_published' => true,
    ]);

    $response = $this->getJson('/search?q=rackets&type=articles');

    $response->assertStatus(200);
    $response->assertJsonPath('articles.0.title', 'Best Tennis Rackets for Beginners');
});

it('returns courts matching search query', function () {
    Court::factory()->create([
        'name' => 'Senayan Tennis Court',
        'city' => 'Jakarta',
        'surface' => 'hard',
    ]);

    Court::factory()->create([
        'name' => 'Kuta Beach Tennis',
        'city' => 'Bali',
        'surface' => 'clay',
    ]);

    $response = $this->getJson('/search?q=Senayan&type=courts');

    $response->assertStatus(200);
    $response->assertJsonPath('courts.0.title', 'Senayan Tennis Court');
});

it('filters results by type', function () {
    Tournament::factory()->create([
        'name' => 'Test Tournament',
        'city' => 'Jakarta',
        'status' => 'open',
    ]);

    Article::factory()->create([
        'title' => 'Test Article',
        'kicker' => 'Test',
        'author' => 'Test',
        'is_published' => true,
    ]);

    Court::factory()->create([
        'name' => 'Test Court',
        'city' => 'Jakarta',
        'surface' => 'hard',
    ]);

    $response = $this->getJson('/search?q=Test&type=tournaments');

    $response->assertStatus(200);
    $response->assertJsonPath('tournaments.0.title', 'Test Tournament');
    $response->assertJsonMissingPath('articles');
    $response->assertJsonMissingPath('courts');
});

it('requires minimum query length of 2 characters', function () {
    $response = $this->getJson('/search?q=a');

    $response->assertStatus(422);
});

it('excludes unpublished articles from results', function () {
    Article::factory()->create([
        'title' => 'Published Article',
        'kicker' => 'Test',
        'author' => 'Test',
        'is_published' => true,
    ]);

    Article::factory()->create([
        'title' => 'Draft Article',
        'kicker' => 'Test',
        'author' => 'Test',
        'is_published' => false,
    ]);

    $response = $this->getJson('/search?q=Article&type=articles');

    $response->assertStatus(200);
    expect($response->json('articles'))->toHaveCount(1);
    expect($response->json('articles.0.title'))->toBe('Published Article');
});

it('excludes closed tournaments from results', function () {
    Tournament::factory()->create([
        'name' => 'Open Tournament',
        'city' => 'Jakarta',
        'status' => 'open',
    ]);

    Tournament::factory()->create([
        'name' => 'Closed Tournament',
        'city' => 'Jakarta',
        'status' => 'closed',
    ]);

    $response = $this->getJson('/search?q=Tournament&type=tournaments');

    $response->assertStatus(200);
    expect($response->json('tournaments'))->toHaveCount(1);
    expect($response->json('tournaments.0.title'))->toBe('Open Tournament');
});

it('returns json response for xhr requests', function () {
    Tournament::factory()->create([
        'name' => 'Test Tournament',
        'city' => 'Jakarta',
        'status' => 'open',
    ]);

    $response = $this->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
    ])->getJson('/search?q=Test&type=tournaments');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/json');
});

it('searches all types when type parameter is all', function () {
    Tournament::factory()->create([
        'name' => 'Search Test Tournament',
        'city' => 'Jakarta',
        'status' => 'open',
    ]);

    Article::factory()->create([
        'title' => 'Search Test Article',
        'kicker' => 'Test',
        'author' => 'Test',
        'is_published' => true,
    ]);

    Court::factory()->create([
        'name' => 'Search Test Court',
        'city' => 'Jakarta',
        'surface' => 'hard',
    ]);

    $response = $this->getJson('/search?q=Search Test&type=all');

    $response->assertStatus(200);
    $response->assertJsonPath('tournaments.0.title', 'Search Test Tournament');
    $response->assertJsonPath('articles.0.title', 'Search Test Article');
    $response->assertJsonPath('courts.0.title', 'Search Test Court');
});

it('returns empty results for non-matching query', function () {
    Tournament::factory()->create([
        'name' => 'Jakarta Open',
        'city' => 'Jakarta',
        'status' => 'open',
    ]);

    $response = $this->getJson('/search?q=NonExistentQuery&type=all');

    $response->assertStatus(200);
    $response->assertJson([]);
});

it('validates query parameter is required', function () {
    $response = $this->getJson('/search?type=all');

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('q');
});

it('limits results to 5 per type', function () {
    Tournament::factory()->count(10)->create([
        'name' => 'Test Tournament',
        'city' => 'Jakarta',
        'status' => 'open',
    ]);

    $response = $this->getJson('/search?q=Test&type=tournaments');

    $response->assertStatus(200);
    expect($response->json('tournaments'))->toHaveCount(5);
});

it('includes canceled tournament with future end date in results', function () {
    Tournament::factory()->canceled()->create([
        'name' => 'Future Canceled Tournament',
        'city' => 'Jakarta',
        'end' => now()->addWeek()->toDateString(),
    ]);

    $response = $this->getJson('/search?q=Future&type=tournaments');

    $response->assertStatus(200);
    expect($response->json('tournaments'))->toHaveCount(1);
    expect($response->json('tournaments.0.title'))->toBe('Future Canceled Tournament');
});

it('excludes canceled tournament whose end date has passed from results', function () {
    Tournament::factory()->canceled()->create([
        'name' => 'Past Canceled Tournament',
        'city' => 'Jakarta',
        'end' => now()->subDay()->toDateString(),
    ]);

    $response = $this->getJson('/search?q=Past&type=tournaments');

    $response->assertStatus(200);
    expect($response->json('tournaments'))->toHaveCount(0);
});

it('excludes canceled tournament with no end date from search results', function () {
    Tournament::factory()->canceled()->create([
        'name' => 'NullEnd Canceled Tournament',
        'city' => 'Jakarta',
        'end' => null,
    ]);

    $response = $this->getJson('/search?q=NullEnd&type=tournaments');

    $response->assertStatus(200);
    expect($response->json('tournaments'))->toHaveCount(0);
});
