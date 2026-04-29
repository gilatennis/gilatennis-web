<?php

use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Newsletter Subscription', function () {
    it('subscribes a new email address', function () {
        $response = $this->postJson('/newsletter/subscribe', [
            'email' => 'tennis-fan@example.com',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'tennis-fan@example.com',
        ]);
    });

    it('rejects duplicate email', function () {
        NewsletterSubscriber::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/newsletter/subscribe', [
            'email' => 'existing@example.com',
        ]);

        $response->assertStatus(422);
    });

    it('rejects invalid email', function () {
        $response = $this->postJson('/newsletter/subscribe', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422);
    });

    it('rejects missing email', function () {
        $response = $this->postJson('/newsletter/subscribe', []);

        $response->assertStatus(422);
    });
});
