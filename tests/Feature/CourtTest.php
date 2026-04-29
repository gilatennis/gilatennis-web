<?php

use App\Models\Court;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Court Model', function () {
    it('can be created via factory', function () {
        $c = Court::factory()->create();
        expect($c->exists())->toBeTrue();
    });

    it('is_video returns true for video content', function () {
        $video = Court::factory()->create(['content_type' => 'video']);
        expect($video->is_video)->toBeTrue();

        $article = Court::factory()->create(['content_type' => 'article']);
        expect($article->is_video)->toBeFalse();
    });

    it('surface_label returns human-readable label', function () {
        $hard = Court::factory()->create(['surface' => 'hard']);
        expect($hard->surface_label)->toBe('Hard');

        $clay = Court::factory()->create(['surface' => 'clay']);
        expect($clay->surface_label)->toBe('Clay');
    });

    it('type_label returns Video or Read', function () {
        $video = Court::factory()->create(['content_type' => 'video']);
        expect($video->type_label)->toBe('Video');

        $article = Court::factory()->create(['content_type' => 'article']);
        expect($article->type_label)->toBe('Read');
    });

    it('featured scope returns only featured courts', function () {
        Court::factory()->create(['is_featured' => true]);
        Court::factory()->create(['is_featured' => false]);
        Court::factory()->create(['is_featured' => true]);

        expect(Court::featured()->count())->toBe(2);
    });

    it('amenities relationship returns amenities', function () {
        $court = Court::factory()->create();
        $court->amenities()->create(['name' => 'Lights']);
        $court->amenities()->create(['name' => 'Pro Shop']);

        expect($court->amenities->count())->toBe(2);
    });
});

describe('CourtAmenity Model', function () {
    it('belongs to court', function () {
        $court = Court::factory()->create();
        $amenity = $court->amenities()->create(['name' => 'Cafe']);

        expect($amenity->court->is($court))->toBeTrue();
    });
});
