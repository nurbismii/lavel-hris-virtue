<?php

namespace App\Providers;

use App\Models\Cuti;
use App\Models\Roster;
use App\Models\RosterOffRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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
        View::composer('partials.sidebar', function ($view) {
            $approvalHodCounts = [
                'cuti' => 0,
                'izin' => 0,
                'roster' => 0,
                'roster_off' => 0,
                'total' => 0,
            ];

            $approvalHrCounts = [
                'cuti' => 0,
                'izin' => 0,
                'roster' => 0,
                'roster_off' => 0,
                'total' => 0,
            ];

            $user = Auth::user();

            if (!$user) {
                $view->with(compact('approvalHodCounts', 'approvalHrCounts'));
                return;
            }

            if ($user->hasMenuAccess('approval_hod')) {
                $approvalHodCounts['cuti'] = $user->applyEmployeeRelationScope(
                    Cuti::query()
                        ->where('tipe', 'CUTI')
                        ->where('status_hod', 0)
                )->count();

                $approvalHodCounts['izin'] = $user->applyEmployeeRelationScope(
                    Cuti::query()
                        ->whereIn('tipe', ['PAID', 'UNPAID'])
                        ->where('status_hod', 0)
                )->count();

                $approvalHodCounts['roster'] = $user->applyEmployeeRelationScope(
                    Roster::query()
                        ->where('status_pengajuan', 0)
                )->count();

                if (Schema::hasTable('roster_off_requests')) {
                    $approvalHodCounts['roster_off'] = $user->applyEmployeeRelationScope(
                        RosterOffRequest::query()
                            ->where('status_hod', RosterOffRequest::STATUS_PENDING)
                    )->count();
                }

                $approvalHodCounts['total'] = $approvalHodCounts['cuti']
                    + $approvalHodCounts['izin']
                    + $approvalHodCounts['roster']
                    + $approvalHodCounts['roster_off'];
            }

            if ($user->hasMenuAccess('approval_hr')) {
                $approvalHrCounts['cuti'] = $user->applyEmployeeRelationScope(
                    Cuti::query()
                        ->where('tipe', 'CUTI')
                        ->where('status_hod', 1)
                        ->where('status_hrd', 0)
                )->count();

                $approvalHrCounts['izin'] = $user->applyEmployeeRelationScope(
                    Cuti::query()
                        ->whereIn('tipe', ['PAID', 'UNPAID'])
                        ->where('status_hod', 1)
                        ->where('status_hrd', 0)
                )->count();

                $approvalHrCounts['roster'] = $user->applyEmployeeRelationScope(
                    Roster::query()
                        ->where('status_pengajuan', 1)
                        ->where('status_pengajuan_hrd', 0)
                )->count();

                if (Schema::hasTable('roster_off_requests')) {
                    $approvalHrCounts['roster_off'] = $user->applyEmployeeRelationScope(
                        RosterOffRequest::query()
                            ->where('status_hod', RosterOffRequest::STATUS_APPROVED)
                            ->where('status_hrd', RosterOffRequest::STATUS_PENDING)
                    )->count();
                }

                $approvalHrCounts['total'] = $approvalHrCounts['cuti']
                    + $approvalHrCounts['izin']
                    + $approvalHrCounts['roster']
                    + $approvalHrCounts['roster_off'];
            }

            $view->with(compact('approvalHodCounts', 'approvalHrCounts'));
        });
    }
}
