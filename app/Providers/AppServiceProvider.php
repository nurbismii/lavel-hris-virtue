<?php

namespace App\Providers;

use App\Services\Approvals\ApprovalSidebarCountService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrap();

        View::composer('partials.sidebar', function ($view) {
            $user = Auth::user();
            $countService = app(ApprovalSidebarCountService::class);

            if (!$user) {
                $view->with($countService->defaultCounts());
                return;
            }

            $view->with($countService->countsFor($user));
        });
    }
}
