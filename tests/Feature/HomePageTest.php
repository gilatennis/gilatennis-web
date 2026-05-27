<?php

use App\Models\Article;
use App\Models\Court;
use App\Models\Division;
use App\Models\Tournament;

describe('HomePage', function () {
    it('loads successfully', function () {
        $response = $this->get('/');
        $response->assertOk();
    });

    it('renders Home component', function () {
        $this->get('/')->assertInertia(fn ($page) => $page->component('Home'));
    });

    it('passes featuredTournaments to view', function () {
        $t = Tournament::factory()->create(['is_featured' => true, 'status' => 'open']);
        Division::factory()->forTournament($t)->singles()->create(['draw_size' => 32, 'fee' => 45000]);

        $response = $this->get('/');
        $response->assertInertia(
            fn ($page) => $page
                ->component('Home')
                ->where('featuredTournaments', fn ($t) => $t->count() === 1)
        );
    });

    it('passes articles to view', function () {
        Article::factory()->create(['is_published' => true, 'published_at' => now()]);

        $response = $this->get('/');
        $response->assertInertia(
            fn ($page) => $page
                ->component('Home')
                ->where('articles', fn ($a) => $a->count() === 1)
        );
    });

    it('passes featuredCourts to view', function () {
        $court = Court::factory()->create(['is_featured' => true]);
        $court->amenities()->create(['name' => 'Pro Shop']);

        $response = $this->get('/');
        $response->assertInertia(
            fn ($page) => $page
                ->component('Home')
                ->where('featuredCourts', fn ($c) => $c->count() === 1)
        );
    });

    it('only shows published articles', function () {
        Article::factory()->create(['is_published' => true, 'published_at' => now(), 'title' => 'Published Article']);
        Article::factory()->create(['is_published' => false, 'title' => 'Draft Article']);

        $response = $this->get('/');
        $response->assertInertia(
            fn ($page) => $page
                ->where('articles.0.title', 'Published Article')
        );
    });

    it('only shows open tournaments', function () {
        $t = Tournament::factory()->create(['name' => 'Open Tournament', 'status' => 'open', 'is_featured' => true]);
        Division::factory()->forTournament($t)->singles()->create(['draw_size' => 32, 'fee' => 45000]);
        Tournament::factory()->create(['name' => 'Closed Tournament', 'status' => 'closed', 'is_featured' => true]);

        $response = $this->get('/');
        $response->assertInertia(
            fn ($page) => $page
                ->where('featuredTournaments.0.name', 'Open Tournament')
        );
    });

    it('includes stats in response', function () {
        $response = $this->get('/');
        $response->assertInertia(
            fn ($page) => $page
                ->where('stats.articleCount', fn ($v) => $v >= 0)
                ->where('stats.tournamentCount', fn ($v) => $v >= 0)
                ->where('stats.memberCount', fn ($v) => $v >= 0)
        );
    });

    it('includes canRegister in response', function () {
        $response = $this->get('/');
        $response->assertInertia(
            fn ($page) => $page
                ->where('canRegister', true)
        );
    });
});
