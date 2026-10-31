<?php

namespace App\Providers;

use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\User;
use App\Support\Navigation\Menu;
use App\Support\Security\BlindIndex;
use App\Support\Tenancy\TenantContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->singleton(BlindIndex::class, fn () => BlindIndex::fromConfig());
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Matriks hak akses PRD §7.
        foreach (config('medic.abilities') as $ability => $roles) {
            Gate::define($ability, fn ($user) => $user instanceof User && in_array($user->role->value, $roles, true));
        }

        View::composer('layouts.app', function ($view) {
            $user = Auth::guard('web')->user();
            $menu = new Menu($user);

            $view->with([
                'authUser' => $user,
                'navSections' => $menu->sections(),
                'palette' => ['pages' => $menu->palette(), 'actions' => []],
            ]);
        });

        // Tema faskes (indigo/teal) dipasang sebagai atribut <html data-nx-brand>.
        View::composer(['layouts.app', 'layouts.auth'], function ($view) {
            $tenant = Auth::guard('web')->user()?->tenant;
            $view->with('brandTheme', $view->getData()['brandTheme'] ?? $tenant?->theme() ?? Tenant::DEFAULT_THEME);
        });
    }
}
