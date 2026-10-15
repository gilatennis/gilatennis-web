<?php

use App\Models\Role;
use App\Models\TournamentRegistration;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

function adminRole(): Role
{
    return Role::where('slug', 'admin')->firstOrFail();
}

describe('Admin User Management', function () {
    it('super admin can list users with role info', function () {
        User::factory()->count(3)->create();
        $adminUser = User::factory()->create();
        $adminUser->roles()->attach(adminRole());

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['role' => '']))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users/Index')
                ->has('users.data', 5)
                ->where('stats.total', 5)
                ->where('stats.admins', 2)
            );
    });

    it('flags users with tournament registrations on index', function () {
        $with = User::factory()->create();
        TournamentRegistration::factory()->create(['user_id' => $with->id]);
        $without = User::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['role' => '']))
            ->assertInertia(fn ($page) => $page
                ->where('users.data', fn ($rows) => collect($rows)
                    ->where('id', $with->id)->first()['has_registrations'] === true
                    && collect($rows)
                        ->where('id', $without->id)->first()['has_registrations'] === false)
            );
    });

    it('only shows admin-role users by default on index', function () {
        User::factory()->count(3)->create();
        $adminUser = User::factory()->create();
        $adminUser->roles()->attach(adminRole());

        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('users.data', 2)
                ->where('stats.total', 5)
                ->where('stats.admins', 2)
            );
    });

    it('can search users by name and email', function () {
        User::factory()->create(['name' => 'Alice Wonder', 'email' => 'alice@test.com']);
        User::factory()->create(['name' => 'Bob Builder', 'email' => 'bob@test.com']);

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['role' => '', 'search' => 'Alice']))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Alice Wonder')
            );
    });

    it('can filter users by role', function () {
        $adminUser = User::factory()->create();
        $adminUser->roles()->attach(adminRole());
        User::factory()->count(2)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['role' => 'admin']))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.role.slug', 'admin')
            );
    });

    it('can filter users by multiple roles', function () {
        $adminUser = User::factory()->create();
        $adminUser->roles()->attach(adminRole());
        User::factory()->count(2)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['role' => ['admin', 'super-admin']]))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('users.data', 2)
            );
    });

    it('normalizes single role filter to array in response', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['role' => 'admin']))
            ->assertInertia(fn ($page) => $page
                ->where('filters.role', ['admin'])
            );
    });

    it('applies default role filter (super-admin + admin + umpire) when no filter is set', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertInertia(fn ($page) => $page
                ->where('filters.role', ['super-admin', 'admin', 'umpire'])
            );
    });

    it('lists umpires by default and counts them in stats', function () {
        User::factory()->withRole(User::ROLE_UMPIRE, 'Umpire')->create();

        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.umpires', 1)
                ->where('users.data', fn ($rows) => collect($rows)
                    ->contains(fn ($row) => $row['role']['slug'] === User::ROLE_UMPIRE))
            );
    });

    it('treats empty string role param as cleared filter', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['role' => '']))
            ->assertInertia(fn ($page) => $page
                ->where('filters.role', [])
            );
    });

    it('super admin can view the create form', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.users.create'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users/Create')
                ->has('availableRoles', 2)
                ->where('availableRoles.1.slug', User::ROLE_UMPIRE)
            );
    });

    it('super admin can create a new user with admin role', function () {
        $data = [
            'name' => 'New Admin',
            'email' => 'newadmin@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), $data)
            ->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'newadmin@test.com')->first();
        expect($user)->not->toBeNull();
        expect($user->name)->toBe('New Admin');
        expect($user->is_guest)->toBeFalse();
        expect($user->email_verified_at)->not->toBeNull();
        expect($user->roles->first()->slug)->toBe('admin');
        expect(Hash::check('password123', $user->password))->toBeTrue();
    });

    it('validates unique email and password confirmation', function () {
        User::factory()->create(['email' => 'taken@test.com']);

        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => 'Dup',
                'email' => 'taken@test.com',
                'password' => 'password123',
                'password_confirmation' => 'different123',
                'role' => 'admin',
            ])
            ->assertSessionHasErrors(['email', 'password']);
    });

    it('validates password minimum length', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => 'Short',
                'email' => 'short@test.com',
                'password' => 'short',
                'password_confirmation' => 'short',
                'role' => 'admin',
            ])
            ->assertSessionHasErrors('password');
    });

    it('super admin can view the edit form', function () {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.users.edit', $user))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users/Edit')
                ->where('user.id', $user->id)
            );
    });

    it('super admin can update user fields and rehash password only when provided', function () {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@test.com',
        ]);
        $oldHash = $user->password;

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $user), [
                'name' => 'New Name',
                'email' => 'new@test.com',
                'password' => '',
                'password_confirmation' => '',
                'role' => '',
            ])
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();
        expect($user->name)->toBe('New Name');
        expect($user->email)->toBe('new@test.com');
        expect($user->password)->toBe($oldHash);
        expect($user->roles)->toHaveCount(0);

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $user), [
                'name' => 'New Name',
                'email' => 'new@test.com',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
                'role' => 'admin',
            ]);

        $user->refresh();
        expect(Hash::check('newpassword123', $user->password))->toBeTrue();
        expect($user->roles->first()->slug)->toBe('admin');
    });

    it('super admin cannot change their own role', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->admin), [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'password' => '',
                'password_confirmation' => '',
                'role' => 'admin',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->admin->refresh();
        expect($this->admin->roles->first()->slug)->toBe('super-admin');
    });

    it('revokes admin role when role field is empty', function () {
        $user = User::factory()->create();
        $user->roles()->attach(adminRole());

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => '',
                'password_confirmation' => '',
                'role' => '',
            ])
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();
        expect($user->roles)->toHaveCount(0);
    });

    it('super admin can delete a user with no data', function () {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        expect(User::find($user->id))->toBeNull();
    });

    it('super admin cannot delete their own account', function () {
        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->admin))
            ->assertRedirect();

        expect(User::find($this->admin->id))->not->toBeNull();
    });

    it('cannot delete a user with tournament registrations', function () {
        $user = User::factory()->create();
        TournamentRegistration::factory()->create(['user_id' => $user->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect();

        expect(User::find($user->id))->not->toBeNull();
    });

    it('prevents deleting the last super admin', function () {
        $otherSuper = User::factory()->create();
        $otherSuper->roles()->attach(Role::where('slug', User::ROLE_SUPER_ADMIN)->firstOrFail());

        $this->actingAs($otherSuper)
            ->delete(route('admin.users.destroy', $otherSuper))
            ->assertRedirect();

        expect(User::find($otherSuper->id))->not->toBeNull();
    });

    it('allows deleting a super admin when another super admin exists', function () {
        $extra = User::factory()->create();
        $extra->roles()->attach(Role::where('slug', User::ROLE_SUPER_ADMIN)->firstOrFail());

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $extra))
            ->assertRedirect(route('admin.users.index'));

        expect(User::find($extra->id))->toBeNull();
    });
});

describe('Admin User Management authorization', function () {
    it('blocks admin role user from user management routes', function () {
        $adminUser = User::factory()->create();
        $adminUser->roles()->attach(adminRole());

        $this->actingAs($adminUser)
            ->get(route('admin.users.index'))
            ->assertStatus(403);

        $this->actingAs($adminUser)
            ->get(route('admin.users.create'))
            ->assertStatus(403);

        $target = User::factory()->create();
        $this->actingAs($adminUser)
            ->get(route('admin.users.edit', $target))
            ->assertStatus(403);

        $this->actingAs($adminUser)
            ->post(route('admin.users.store'), [
                'name' => 'X',
                'email' => 'x@test.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'admin',
            ])
            ->assertStatus(403);
    });

    it('blocks non-admin from user management routes', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertStatus(403);
    });

    it('redirects guests to admin login', function () {
        $this->get(route('admin.users.index'))
            ->assertRedirect('/admin/login');
    });
});

describe('User model role helpers', function () {
    it('isAdminRole returns true for super-admin and admin slugs', function () {
        $super = User::factory()->admin()->create();
        $admin = User::factory()->create();
        $admin->roles()->attach(adminRole());
        $regular = User::factory()->create();

        expect($super->isAdminRole())->toBeTrue();
        expect($admin->isAdminRole())->toBeTrue();
        expect($regular->isAdminRole())->toBeFalse();
    });

    it('isSuperAdmin returns true only for super-admin slug', function () {
        $super = User::factory()->admin()->create();
        $admin = User::factory()->create();
        $admin->roles()->attach(adminRole());

        expect($super->isSuperAdmin())->toBeTrue();
        expect($admin->isSuperAdmin())->toBeFalse();
    });
});
