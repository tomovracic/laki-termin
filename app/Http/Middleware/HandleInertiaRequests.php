<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use App\Services\AppSettingService;
use App\Services\Groups\UserGroupPermissionResolver;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $permissionResolver = app(UserGroupPermissionResolver::class);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'isAdmin' => $user?->hasRole('admin') ?? false,
                'canAccessMatchHistory' => $user !== null
                    ? $permissionResolver->canAccessMatchHistory($user)
                    : false,
                'canAccessRanking' => $user !== null
                    ? $permissionResolver->canAccessRanking($user)
                    : false,
                'canViewAllRankingGroups' => $user !== null
                    ? $permissionResolver->canViewAllRankingGroups($user)
                    : false,
            ],
            'locale' => app()->getLocale(),
            'availableLocales' => Locale::options(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'loginMessage' => fn () => $request->user() !== null
                ? $request->session()->get('login_message')
                : null,
            'nav' => function () use ($request) {
                if ($request->user() === null) {
                    return null;
                }

                $terrainUsageRules = app(AppSettingService::class)->getTerrainUsageRules();

                return [
                    'token_count' => $request->user()->token_count,
                    'terrain_usage_rules' => $terrainUsageRules,
                    'must_acknowledge_terrain_usage_rules' => $terrainUsageRules !== []
                        && $request->user()->terrain_usage_rules_acknowledged_at === null,
                ];
            },
        ];
    }
}
