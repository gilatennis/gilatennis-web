<?php

use App\Models\Role;
use App\Models\Settings;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
    $this->admin->roles()->syncWithoutDetaching([$role->id]);
});

describe('Admin System Settings', function () {
    it('admin can view system settings page', function () {
        Settings::create(['contact_wa_number' => '+6281234567890']);

        $this->actingAs($this->admin)
            ->get(route('admin.settings'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Settings/System')
                ->where('contactWaNumber', '+6281234567890')
            );
    });

    it('admin can update whatsapp number', function () {
        Settings::create(['contact_wa_number' => '+6281234567890']);

        $this->actingAs($this->admin)
            ->patch(route('admin.settings.update'), [
                'contact_wa_number' => '+6289998887776',
            ])
            ->assertRedirect(route('admin.settings'));

        expect(Settings::first()->contact_wa_number)->toBe('+6289998887776');
    });

    it('admin can update whatsapp number when no settings exist', function () {
        $this->actingAs($this->admin)
            ->patch(route('admin.settings.update'), [
                'contact_wa_number' => '+6289998887776',
            ])
            ->assertRedirect(route('admin.settings'));

        expect(Settings::first()->contact_wa_number)->toBe('+6289998887776');
    });

    it('non-admin cannot access system settings', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.settings'))
            ->assertStatus(403);

        $this->actingAs($user)
            ->patch(route('admin.settings.update'), [
                'contact_wa_number' => '+6289998887776',
            ])
            ->assertStatus(403);
    });

    it('guest cannot access system settings', function () {
        $this->get(route('admin.settings'))
            ->assertRedirect(route('admin.login'));

        $this->patch(route('admin.settings.update'), [
            'contact_wa_number' => '+6289998887776',
        ])
            ->assertRedirect(route('admin.login'));
    });

    it('validates required whatsapp number', function () {
        $this->actingAs($this->admin)
            ->patch(route('admin.settings.update'), [
                'contact_wa_number' => '',
            ])
            ->assertSessionHasErrors('contact_wa_number');
    });
});
