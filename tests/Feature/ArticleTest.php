<?php

use App\Models\Article;

describe('Article Model', function () {
    it('can be created via factory', function () {
        $a = Article::factory()->create();
        expect($a->exists())->toBeTrue();
    });

    it('type_badge_variant returns correct variant per type', function () {
        $review = Article::factory()->create(['type' => 'review']);
        expect($review->type_badge_variant)->toBe('coral');

        $tip = Article::factory()->create(['type' => 'tip']);
        expect($tip->type_badge_variant)->toBe('default');

        $strategy = Article::factory()->create(['type' => 'strategy']);
        expect($strategy->type_badge_variant)->toBe('secondary');

        $fitness = Article::factory()->create(['type' => 'fitness']);
        expect($fitness->type_badge_variant)->toBe('ghost');
    });

    it('scope_published returns only published articles', function () {
        Article::factory()->create(['is_published' => true, 'published_at' => now()]);
        Article::factory()->create(['is_published' => true, 'published_at' => null]);
        Article::factory()->create(['is_published' => false, 'published_at' => now()]);

        expect(Article::published()->count())->toBe(1);
    });

    it('scope_of_type filters by type', function () {
        Article::factory()->create(['type' => 'review']);
        Article::factory()->create(['type' => 'tip']);
        Article::factory()->create(['type' => 'review']);

        expect(Article::ofType('review')->count())->toBe(2);
        expect(Article::ofType('tip')->count())->toBe(1);
    });

    it('scope_reviews uses ofType', function () {
        Article::factory()->create(['type' => 'review']);
        Article::factory()->create(['type' => 'tip']);

        expect(Article::reviews()->count())->toBe(1);
    });

    it('scope_tips uses ofType', function () {
        Article::factory()->create(['type' => 'tip']);
        Article::factory()->create(['type' => 'review']);

        expect(Article::tips()->count())->toBe(1);
    });

    it('products relationship returns article products', function () {
        $article = Article::factory()->create();
        $article->products()->create(['name' => 'Pro Staff 97', 'price_label' => '$259']);
        $article->products()->create(['name' => 'Luxilon ALU', 'price_label' => '$22']);

        expect($article->products->count())->toBe(2);
    });
});

describe('ArticleProduct Model', function () {
    it('belongs to article', function () {
        $article = Article::factory()->create();
        $product = $article->products()->create(['name' => 'EZONE 100', 'price_label' => '$229']);

        expect($product->article->is($article))->toBeTrue();
    });
});
