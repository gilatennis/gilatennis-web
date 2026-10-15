<?php

use App\Enums\MatchStatus;
use App\Models\TennisMatch;
use App\Models\User;

it('sends a match to court and recalls it from the admin match list', function () {
    $admin = User::factory()->admin()->create();

    $match = TennisMatch::factory()->create([
        'side_a_name' => 'List A',
        'side_b_name' => 'List B',
    ]);

    $page = visit('/admin/login')
        ->fill('email', $admin->email)
        ->fill('password', 'password')
        ->click('Sign in')
        ->wait(1)
        ->assertPathIs('/admin/dashboard')
        ->navigate('/admin/tennis-matches')
        ->wait(2)
        ->assertSee('List A')
        ->assertSeeIn('@match-send-court', 'Send to court');

    $page->click('@match-send-court')->wait(2);
    expect($match->fresh()->status)->toBe(MatchStatus::Running);

    $page->click('@match-recall')->wait(2);
    expect($match->fresh()->status)->toBe(MatchStatus::Pending);

    $page->assertNoJavaScriptErrors();
});
