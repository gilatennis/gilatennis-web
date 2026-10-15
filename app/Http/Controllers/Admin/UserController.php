<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $roleFilter = $this->resolveRoleFilter();

        $query = User::query()
            ->with('roles')
            ->withExists('tournamentRegistrations');

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($roleFilter !== []) {
            $query->whereHas('roles', fn ($q) => $q->whereIn('slug', $roleFilter));
        }

        $users = $query->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user) => $this->shape($user));

        $stats = [
            'total' => User::count(),
            'admins' => User::whereHas('roles', fn ($q) => $q->whereIn('slug', [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN]))->count(),
            'umpires' => User::whereHas('roles', fn ($q) => $q->where('slug', User::ROLE_UMPIRE))->count(),
        ];

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'role' => $roleFilter,
            ],
            'availableRoles' => $this->availableRoles(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'availableRoles' => $this->availableRoles(),
        ]);
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => $request->input('password'),
                'email_verified_at' => now(),
                'is_guest' => false,
            ]);

            $this->syncRole($user, $request->input('role'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User created.']);

        return redirect()->route('admin.users.index');
    }

    public function edit(User $user): Response
    {
        $user->load('roles');

        return Inertia::render('Admin/Users/Edit', [
            'user' => $this->shape($user),
            'availableRoles' => $this->availableRoles(),
        ]);
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user) {
            $user->fill($request->safe()->only(['name', 'email']));

            if ($request->filled('password')) {
                $user->password = $request->input('password');
            }

            $user->save();

            if (auth()->id() !== $user->id) {
                $this->syncRole($user, $request->input('role'));
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User updated.']);

        return redirect()->route('admin.users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        if (auth()->id() === $user->id) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'You cannot delete your own account.']);

            return back();
        }

        if ($user->isSuperAdmin() && $this->countSuperAdmins() <= 1) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Cannot delete the last super admin.']);

            return back();
        }

        if ($user->tournamentRegistrations()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Cannot delete: this user has tournament registrations. Demote the admin role instead.',
            ]);

            return back();
        }

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User deleted.']);

        return redirect()->route('admin.users.index');
    }

    private function shape(User $user): array
    {
        $role = $user->roles->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_guest' => $user->is_guest,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
            'role' => $role ? [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ] : null,
            'is_super_admin' => $user->isSuperAdmin(),
            'is_admin' => $user->isAdminRole(),
            'has_registrations' => (bool) $user->tournament_registrations_exists,
        ];
    }

    private function availableRoles(): array
    {
        return collect(User::ASSIGNABLE_ROLES)
            ->map(fn (string $slug): array => [
                'slug' => $slug,
                'name' => User::ROLE_LABELS[$slug],
            ])
            ->values()
            ->all();
    }

    private function resolveRoleFilter(): array
    {
        $query = request()->query->all();

        if (! array_key_exists('role', $query)) {
            return array_keys(User::ROLE_LABELS);
        }

        $raw = $query['role'];

        if (is_array($raw)) {
            return array_values(array_filter($raw, fn ($v) => is_string($v) && $v !== ''));
        }

        if (is_string($raw) && $raw !== '') {
            return [$raw];
        }

        return [];
    }

    private function syncRole(User $user, ?string $newRoleSlug): void
    {
        if ($newRoleSlug === null || $newRoleSlug === '') {
            $user->roles()->detach();

            return;
        }

        $role = Role::where('slug', $newRoleSlug)->firstOrFail();

        $user->roles()->sync([$role->id]);
    }

    private function countSuperAdmins(): int
    {
        return User::whereHas('roles', fn ($q) => $q->where('slug', User::ROLE_SUPER_ADMIN))->count();
    }
}
