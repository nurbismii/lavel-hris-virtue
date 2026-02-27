<?php

use App\Http\Controllers\Admin\ApiController;
use App\Http\Controllers\Admin\SettingRoleController;
use App\Http\Controllers\Admin\SlipGajiController;
use App\Http\Controllers\Approval\CutiApprovalController;
use App\Http\Controllers\Approval\IzinApprovalController;
use App\Http\Controllers\Approval\RosterApprovalController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\PresensiController;
use App\Http\Controllers\Admin\PresensiController as PresensiAdminController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/mobile-logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/login?app=V-PEOPLE');
});

Route::get('forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');

Route::view('/download-app', 'download-app');

Route::middleware(['android.redirect'])->group(function () {

    Route::get('/', function () {
        return view('auth.login');
    });

    Route::middleware('guest')->group(function () {
        Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
        Route::post('/register', [RegisterController::class, 'register']);
    });

    Route::middleware('auth')->group(function () {
        Route::get('/email/verify', function () {
            return view('auth.verify-email');
        })->name('verification.notice');

        Route::post('/email/verification-notification', function (Request $request) {
            $request->user()->sendEmailVerificationNotification();

            return back()->with('message', 'Verification link sent!');
        })->middleware(['auth', 'throttle:6,1'])
            ->name('verification.send');

        Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {

            $request->fulfill();
            // Update status menjadi aktif
            $request->user()->update([
                'status' => 'aktif',
            ]);

            toast()->success('Success', 'Email berhasil diverifikasi.');
            return redirect('/dashboard');
        })->middleware(['auth', 'signed'])->name('verification.verify');
    });

    Auth::routes();

    Route::group(['prefix' => '/', 'middleware' => ['role:User,Administrator,HR', 'verify.email']], function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.karyawan');
        Route::resource('/cuti', 'App\Http\Controllers\User\CutiController');
        Route::resource('/presensi', 'App\Http\Controllers\User\PresensiController')->except('store');
        Route::post('/absen/{type}', [PresensiController::class, 'store'])->middleware('auth');
        Route::resource('/izin', 'App\Http\Controllers\User\IzinController');
        Route::resource('/roster', 'App\Http\Controllers\User\RosterController');
        Route::resource('/slipgaji', 'App\Http\Controllers\User\SlipgajiController');

        Route::resource('/pengaturan-akun', 'App\Http\Controllers\User\PengaturanAkunController')->except(['show']);
        Route::get('/pengaturan-akun/update', [App\Http\Controllers\User\PengaturanAkunController::class, 'SetIndex'])->name('update.akun');

        Route::resource('/kotak-masuk', 'App\Http\Controllers\User\InboxController');
        Route::post('/notif/read-all', function () {
            auth()->user()->unreadNotifications->markAsRead();
            return back();
        })->name('notif.readAll');

        Route::get('/notif/{id}/baca', function ($id) {
            $notif = auth()->user()->notifications()->findOrFail($id);
            $notif->markAsRead();

            return redirect($notif->data['url']);
        })->name('notif.baca');
    });

    Route::group(['prefix' => 'admin', 'middleware' => ['redirect.role', 'auth', 'role:Administrator,HR']], function () {

        Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

        Route::resource('/karyawan', 'App\Http\Controllers\Admin\KaryawanController');

        Route::resource('/user', 'App\Http\Controllers\Admin\UserController');
        Route::resource('/slip-gaji', 'App\Http\Controllers\Admin\SlipGajiController');
        Route::get('/slip-gaji/{id}/pdf', [SlipGajiController::class, 'exportPdf'])->name('slip-gaji.pdf');
        Route::resource('/perusahaan', 'App\Http\Controllers\Admin\PerusahaanController');

        // === DEPARTEMEN ===
        Route::get('/departemen/{perusahaan_id}', [App\Http\Controllers\Admin\DepartemenController::class, 'create'])->name('departemen.create');
        Route::post('/departemen/store', [App\Http\Controllers\Admin\DepartemenController::class, 'store'])->name('departemen.store');
        Route::delete('/departemen/destroy/{id}', [App\Http\Controllers\Admin\DepartemenController::class, 'destroy'])->name('departemen.destroy');
        // === END DEPARTEMEN ===

        // === DIVISI ===
        Route::get('/divisi/create/{perusahaan_id}', [App\Http\Controllers\Admin\DivisiController::class, 'create'])->name('divisi.create');
        Route::post('/divisi/store', [App\Http\Controllers\Admin\DivisiController::class, 'store'])->name('divisi.store');
        Route::delete('/divisi/destroy/{id}', [App\Http\Controllers\Admin\DivisiController::class, 'destroy'])->name('divisi.destroy');
        //=== END DIVISI ===

        Route::resource('/resign', 'App\Http\Controllers\Admin\ResignController');
        Route::resource('/surat-peringatan', 'App\Http\Controllers\Admin\SuratPeringatanController');

        Route::resource('/setting-lokasi-presensi', 'App\Http\Controllers\Admin\SettingLokasiPresensiController');

        // === ROLE ===
        Route::resource('/setting-role', '\App\Http\Controllers\Admin\SettingRoleController');
        Route::patch('/role/update/{id}', [SettingRoleController::class, 'updateRole'])->name('role.update');
        // === END ROLE ===

        Route::resource('/data-presensi', 'App\Http\Controllers\Admin\PresensiController');

        Route::get('/ajax/departemen-by-area', [App\Http\Controllers\Admin\KaryawanController::class, 'departemenByArea'])->name('ajax.departemen.by.area');
        Route::get('/ajax/divisi-by-departemen', [App\Http\Controllers\Admin\KaryawanController::class, 'divisiByDepartemen'])->name('ajax.divisi.by.departemen');

        Route::get('fetch/data-presensi', [PresensiAdminController::class, 'dataPresensi'])->name('fetch.data-presensi');
        Route::get('/presensi/export', [PresensiAdminController::class, 'export'])->name('presensi.export');

        Route::prefix('third-party')->group(function () {
            Route::resource('/search-by-security', 'App\Http\Controllers\SearchBySecurity\UserController');
            Route::resource('/search-logs', 'App\Http\Controllers\SearchBySecurity\SearchLogController');
        });
    });

    Route::group(['prefix' => 'approval', 'middleware' => ['auth', 'role:Administrator,HOD,HR']], function () {

        Route::get('/hod/cuti', [CutiApprovalController::class, 'hodIndex'])->name('approval.cuti.hod');
        Route::post('/hod/cuti{id}', [CutiApprovalController::class, 'hodProcess'])->name('approval.cuti.hod.process');

        Route::get('/hrd/cuti', [CutiApprovalController::class, 'hrdIndex'])->name('approval.cuti.hrd');
        Route::post('/hrd/cuti{id}', [CutiApprovalController::class, 'hrdProcess'])->name('approval.cuti.hrd.process');

        Route::get('/hod/cuti-roster', [RosterApprovalController::class, 'hodIndex'])->name('approval.roster.hod');
        Route::post('/hod/cuti-roster/{id}', [RosterApprovalController::class, 'hodProcess'])->name('approval.roster.hod.process');
        Route::get('/hod/show/cuti-roster/{id}', [RosterApprovalController::class, 'hodShow'])->name('approval.roster.hod.show');

        Route::get('/hrd/cuti-roster', [RosterApprovalController::class, 'hrdIndex'])->name('approval.roster.hrd');
        Route::post('/hrd/cuti-roster/{id}', [RosterApprovalController::class, 'hrdProcess'])->name('approval.roster.hrd.process');

        Route::get('/hod/izin', [IzinApprovalController::class, 'hodIndex'])->name('approval.izin.hod');
        Route::post('/hod/izin{id}', [IzinApprovalController::class, 'hodProcess'])->name('approval.izin.hod.process');

        Route::get('/hrd/izin', [IzinApprovalController::class, 'hrdIndex'])->name('approval.izin.hrd');
        Route::post('/hrd/izin{id}', [IzinApprovalController::class, 'hrdProcess'])->name('approval.izin.hrd.process');
    });
});

Route::group(['prefix' => 'wilayah'], function () {
    Route::resource('/distribusi', 'App\Http\Controllers\Admin\WilayahController');
    Route::get('/provinces', [App\Http\Controllers\Admin\WilayahController::class, 'provinsi'])->name('wilayah.provinces');
    Route::get('/kabupatens/{provinceId}', [App\Http\Controllers\Admin\WilayahController::class, 'kabupaten'])->name('wilayah.kabupatens');
    Route::get('/kecamatans/{kabupatenId}', [App\Http\Controllers\Admin\WilayahController::class, 'kecamatan'])->name('wilayah.kecamatans');
    Route::get('/kelurahans/{kecamatanId}', [App\Http\Controllers\Admin\WilayahController::class, 'kelurahan'])->name('wilayah.kelurahans');
});

Route::group(['prefix' => 'api/'], function () {
    route::get('/airports', [ApiController::class, 'getAirport']);
    Route::post('/gps-log', [PresensiController::class, 'logGps'])->middleware('auth');
});

// SEARCH RIWAYAT KARYAWAN RESIGN
Route::get('search-by-security', [App\Http\Controllers\Admin\ResignController::class, 'search'])->name('search.by.security');
