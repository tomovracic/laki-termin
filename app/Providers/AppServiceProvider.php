<?php

namespace App\Providers;

use App\Models\Reservation;
use App\Models\Terrain;
use App\Models\TerrainInactivePeriod;
use App\Models\User;
use App\Policies\ReservationPolicy;
use App\Policies\TerrainInactivePeriodPolicy;
use App\Policies\TerrainPolicy;
use App\Policies\UserPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        $this->configureDefaults();
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Terrain::class, TerrainPolicy::class);
        Gate::policy(TerrainInactivePeriod::class, TerrainInactivePeriodPolicy::class);
        Gate::policy(Reservation::class, ReservationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Schema::defaultStringLength(191);

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
}
