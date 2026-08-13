<?php

use App\Models\Division;
use App\Models\Tournament;

describe('Home page', function () {
    it('shows featured open tournament', function () {
        Tournament::factory()->create([
            'name' => 'Open Featured',
            'is_featured' => true,
            'status' => 'open',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->where('featuredTournaments.0.name', 'Open Featured')
                ->where('featuredTournaments.0.status', 'open')
            );
    });

    it('shows featured canceled tournament whose end date is in the future', function () {
        Tournament::factory()->canceled()->create([
            'name' => 'Future Canceled Featured',
            'is_featured' => true,
            'end' => now()->addWeek()->toDateString(),
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->where('featuredTournaments.0.name', 'Future Canceled Featured')
                ->where('featuredTournaments.0.status', 'canceled')
            );
    });

    it('hides featured canceled tournament whose end date has passed', function () {
        Tournament::factory()->canceled()->create([
            'name' => 'Past Canceled Featured',
            'is_featured' => true,
            'end' => now()->subDay()->toDateString(),
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->where('featuredTournaments', [])
            );
    });

    it('hides featured canceled tournament with null end date', function () {
        Tournament::factory()->canceled()->create([
            'name' => 'Null End Canceled Featured',
            'is_featured' => true,
            'end' => null,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->where('featuredTournaments', [])
            );
    });

    it('hides non-canceled tournaments that are not open (regression)', function () {
        Tournament::factory()->create(['is_featured' => true, 'status' => 'draft']);
        Tournament::factory()->create(['is_featured' => true, 'status' => 'closed']);
        Tournament::factory()->create(['is_featured' => true, 'status' => 'completed']);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->where('featuredTournaments', [])
            );
    });

    it('hides non-featured open tournaments (regression)', function () {
        Tournament::factory()->create([
            'name' => 'Non Featured Open',
            'is_featured' => false,
            'status' => 'open',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->where('featuredTournaments', [])
            );
    });

    it('hides non-featured canceled tournaments (featured-only rule)', function () {
        Tournament::factory()->canceled()->create([
            'name' => 'Non Featured Canceled',
            'is_featured' => false,
            'end' => now()->addWeek()->toDateString(),
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->where('featuredTournaments', [])
            );
    });

    it('stats tournamentCount counts only open tournaments with divisions', function () {
        $open = Tournament::factory()->create(['status' => 'open', 'is_featured' => true]);
        Division::factory()->forTournament($open)->singles()->create(['draw_size' => 32, 'fee' => 50000]);

        Tournament::factory()->create(['status' => 'open', 'is_featured' => true]);
        Tournament::factory()->canceled()->create([
            'is_featured' => true,
            'end' => now()->addWeek()->toDateString(),
        ]);
        Tournament::factory()->create(['status' => 'closed', 'is_featured' => true]);

        $this->get(route('home'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.tournamentCount', 1)
            );
    });
});
