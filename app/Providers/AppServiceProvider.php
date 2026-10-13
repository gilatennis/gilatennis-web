<?php

namespace App\Providers;

use App\Models\User;
use App\Services\DuitkuService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DuitkuService::class, function () {
            return new DuitkuService(
                config('services.duitku.merchant_code'),
                config('services.duitku.api_key'),
                config('services.duitku.api_url'),
            );
        });
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGates();

        if (app()->environment('e2e')) {
            Vite::useHotFile(storage_path('framework/vite.hot.disabled'));
        }
    }

    protected function configureDefaults(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureGates(): void
    {
        Gate::define('access-admin', fn (User $user) => $user->isAdminRole());
        Gate::define('manage-users', fn (User $user) => $user->isSuperAdmin());
    }
}
