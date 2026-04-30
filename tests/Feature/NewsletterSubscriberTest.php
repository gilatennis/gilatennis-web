<?php

use App\Models\NewsletterSubscriber;

describe('NewsletterSubscriber Model', function () {
    it('can be created via factory', function () {
        $s = NewsletterSubscriber::factory()->create();
        expect($s->exists())->toBeTrue();
    });

    it('active scope excludes unsubscribed', function () {
        NewsletterSubscriber::factory()->create();
        NewsletterSubscriber::factory()->create();

        expect(NewsletterSubscriber::active()->count())->toBe(2);
    });

    it('is_subscribed returns true when not unsubscribed', function () {
        $s = NewsletterSubscriber::factory()->create();
        expect($s->isSubscribed())->toBeTrue();
    });

    it('unsubscribe sets unsubscribed_at', function () {
        $s = NewsletterSubscriber::factory()->create();
        $s->unsubscribe();

        expect($s->unsubscribed_at)->not->toBeNull();
    });

    it('unsubscribed state sets unsubscribed_at', function () {
        $s = NewsletterSubscriber::factory()->create(['unsubscribed_at' => now()]);
        expect($s->unsubscribed_at)->not->toBeNull();
    });
});
