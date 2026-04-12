<?php

namespace App\Providers;

use App\Models\Cuti;
use App\Models\Roster;
use Illuminate\Support\Facades\Auth;
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
                'total' => 0,
            ];

            $approvalHrCounts = [
                'cuti' => 0,
                'izin' => 0,
                'roster' => 0,
                'total' => 0,
            ];

            $user = Auth::user();

            if (!$user) {
                $view->with(compact('approvalHodCounts', 'approvalHrCounts'));
                return;
            }

            $divisiId = optional($user->employee)->divisi_id;

            if ($user->hasRole(['Administrator', 'HOD', 'HR']) && $divisiId) {
                $approvalHodCounts['cuti'] = Cuti::query()
                    ->where('tipe', 'CUTI')
                    ->where('status_hod', 0)
                    ->whereHas('employee', function ($query) use ($divisiId) {
                        $query->where('divisi_id', $divisiId);
                    })
                    ->count();

                $approvalHodCounts['izin'] = Cuti::query()
                    ->whereIn('tipe', ['PAID', 'UNPAID'])
                    ->where('status_hod', 0)
                    ->whereHas('employee', function ($query) use ($divisiId) {
                        $query->where('divisi_id', $divisiId);
                    })
                    ->count();

                $approvalHodCounts['roster'] = Roster::query()
                    ->where('status_pengajuan', 0)
                    ->whereHas('employee', function ($query) use ($divisiId) {
                        $query->where('divisi_id', $divisiId);
                    })
                    ->count();

                $approvalHodCounts['total'] = $approvalHodCounts['cuti']
                    + $approvalHodCounts['izin']
                    + $approvalHodCounts['roster'];
            }

            if ($user->hasRole(['Administrator', 'HR'])) {
                $approvalHrCounts['cuti'] = Cuti::query()
                    ->where('tipe', 'CUTI')
                    ->where('status_hod', 1)
                    ->where('status_hrd', 0)
                    ->count();

                $approvalHrCounts['izin'] = Cuti::query()
                    ->whereIn('tipe', ['PAID', 'UNPAID'])
                    ->where('status_hod', 1)
                    ->where('status_hrd', 0)
                    ->count();

                $approvalHrCounts['roster'] = Roster::query()
                    ->where('status_pengajuan', 1)
                    ->where('status_pengajuan_hrd', 0)
                    ->count();

                $approvalHrCounts['total'] = $approvalHrCounts['cuti']
                    + $approvalHrCounts['izin']
                    + $approvalHrCounts['roster'];
            }

            $view->with(compact('approvalHodCounts', 'approvalHrCounts'));
        });
    }
}
