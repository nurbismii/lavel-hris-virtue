<?php

use App\Http\Controllers\Admin\ApiController;
use App\Http\Controllers\Admin\NationalHolidayController;
use App\Http\Controllers\Admin\OvertimeMasterController;
use App\Http\Controllers\Admin\OvertimeOrderController as AdminOvertimeOrderController;
use App\Http\Controllers\Admin\SettingRoleController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\SlipGajiController;
use App\Http\Controllers\Admin\WorkPatternController;
use App\Http\Controllers\Approval\CutiApprovalController;
use App\Http\Controllers\Approval\IzinApprovalController;
use App\Http\Controllers\Approval\RosterOffApprovalController;
use App\Http\Controllers\Approval\RosterApprovalController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\NotificationController;
use App\Http\Controllers\User\OvertimeOrderController as UserOvertimeOrderController;
use App\Http\Controllers\User\PresensiController;
use App\Http\Controllers\User\RosterOffRequestController;
use App\Http\Controllers\User\SlipgajiController as UserSlipGajiController;
use App\Http\Controllers\Admin\PresensiController as PresensiAdminController;
use App\Http\Controllers\AdminDivisi\AttendanceSettingController;
use App\Http\Controllers\AdminDivisi\ShiftSettingController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\LocaleController;
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

Route::post('/locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

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
        if (config('hris.self_registration_enabled')) {
            Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
            Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:6,1');
        }
    });

    Route::middleware('auth')->group(function () {
        Route::get('/employee-photo/{nik}', [App\Http\Controllers\Admin\KaryawanController::class, 'photo'])
            ->name('employee.photo');

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

    Auth::routes([
        'register' => false,
        'reset' => false,
    ]);

    Route::group(['prefix' => '/', 'middleware' => ['auth', 'verify.email']], function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('menu:dashboard_karyawan')->name('dashboard.karyawan');
        Route::resource('/cuti', 'App\Http\Controllers\User\CutiController')->middleware('menu:cuti');
        Route::get('/presensi/face-reference', [PresensiController::class, 'faceReference'])
            ->middleware('menu:presensi')
            ->name('presensi.face-reference');
        Route::resource('/presensi', 'App\Http\Controllers\User\PresensiController')
            ->only(['index'])
            ->middleware('menu:presensi');
        Route::post('/absen/{type}', [PresensiController::class, 'store'])->middleware(['auth', 'menu:presensi', 'throttle:presensi']);
        Route::get('/izin/{izin}/bukti', [App\Http\Controllers\User\IzinController::class, 'proof'])
            ->middleware('menu:izin')
            ->name('izin.proof');
        Route::resource('/izin', 'App\Http\Controllers\User\IzinController')->middleware('menu:izin');
        Route::get('/roster/{roster}/attachment', [App\Http\Controllers\User\RosterController::class, 'attachment'])
            ->middleware(['menu:roster', 'role:Staff Roster,Super Admin'])
            ->name('roster.attachment');
        Route::resource('/roster', 'App\Http\Controllers\User\RosterController')
            ->middleware(['menu:roster', 'role:Staff Roster,Super Admin']);
        Route::get('/roster-off/effective-dates', [RosterOffRequestController::class, 'effectiveDates'])
            ->middleware(['menu:off_roster', 'role:Staff Roster,Super Admin'])
            ->name('roster-off.effective-dates');
        Route::resource('/roster-off', RosterOffRequestController::class)
            ->only(['index', 'store', 'destroy'])
            ->parameters(['roster-off' => 'rosterOff'])
            ->middleware(['menu:off_roster', 'role:Staff Roster,Super Admin']);
        Route::resource('/slipgaji', UserSlipGajiController::class)
            ->only(['index', 'show'])
            ->middleware('menu:slip_gaji_user');
        Route::get('/slipgaji/{id}/pdf', [UserSlipGajiController::class, 'exportPdf'])->middleware('menu:slip_gaji_user')->name('slipgaji.pdf');
        Route::get('/lembur', [UserOvertimeOrderController::class, 'index'])->middleware('menu:lembur')->name('lembur.index');
        Route::post('/lembur/{id}/respond', [UserOvertimeOrderController::class, 'respond'])->middleware('menu:lembur')->name('lembur.respond');

        Route::resource('/pengaturan-akun', 'App\Http\Controllers\User\PengaturanAkunController')
            ->only(['index', 'store']);
        Route::get('/pengaturan-akun/update', [App\Http\Controllers\User\PengaturanAkunController::class, 'SetIndex'])->name('update.akun');

        Route::resource('/kotak-masuk', 'App\Http\Controllers\User\InboxController')
            ->only(['index']);
        Route::get('/notifications/latest', [NotificationController::class, 'latest'])->name('notifications.latest');
        Route::post('/notif/read-all', [NotificationController::class, 'readAll'])->name('notif.readAll');
        Route::get('/notif/{id}/baca', [NotificationController::class, 'read'])->name('notif.baca');
    });

    Route::group(['prefix' => 'admin', 'middleware' => ['redirect.role', 'auth']], function () {

        Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->middleware('menu:dashboard_admin')->name('home');
        Route::get('/home/upload-progress', [App\Http\Controllers\HomeController::class, 'uploadProgress'])->middleware('menu:dashboard_admin')->name('home.upload-progress');
        Route::delete('/home/upload-progress/{importId}', [App\Http\Controllers\HomeController::class, 'destroyUploadProgress'])->middleware('menu:dashboard_admin')->name('home.upload-progress.destroy');

        Route::post('/karyawan/bulk-upload-documents', [App\Http\Controllers\Admin\KaryawanController::class, 'bulkUploadDocuments'])
            ->middleware('menu:data_karyawan')
            ->name('karyawan.bulk-upload-documents');
        Route::get('/karyawan/{nik}/documents/{type}/preview', [App\Http\Controllers\Admin\KaryawanController::class, 'previewDocument'])
            ->middleware('menu:data_karyawan')
            ->name('karyawan.documents.preview');
        Route::get('/karyawan/{nik}/documents/{type}/download', [App\Http\Controllers\Admin\KaryawanController::class, 'downloadDocument'])
            ->middleware('menu:data_karyawan')
            ->name('karyawan.documents.download');
        Route::get('/karyawan/{nik}/recruitment-documents', [App\Http\Controllers\Admin\KaryawanController::class, 'recruitmentDocuments'])
            ->middleware('menu:data_karyawan')
            ->name('karyawan.recruitment-documents');
        Route::resource('/karyawan', 'App\Http\Controllers\Admin\KaryawanController')
            ->only(['index', 'store', 'edit', 'update', 'destroy'])
            ->middleware('menu:data_karyawan');

        Route::resource('/user', 'App\Http\Controllers\Admin\UserController')
            ->only(['index', 'edit', 'update', 'destroy'])
            ->middleware('menu:data_user');
        Route::resource('/slip-gaji', 'App\Http\Controllers\Admin\SlipGajiController')
            ->only(['index', 'show'])
            ->middleware('menu:slip_gaji_admin');
        Route::get('/slip-gaji/{id}/pdf', [SlipGajiController::class, 'exportPdf'])->middleware('menu:slip_gaji_admin')->name('slip-gaji.pdf');
        Route::resource('/perusahaan', 'App\Http\Controllers\Admin\PerusahaanController')
            ->only(['index', 'show', 'edit', 'update'])
            ->middleware('menu:perusahaan');
        Route::delete('/perusahaan/{id}', [App\Http\Controllers\Admin\PerusahaanController::class, 'delete'])
            ->middleware('menu:perusahaan')
            ->name('perusahaan.destroy');

        // === DEPARTEMEN ===
        Route::get('/departemen/{perusahaan_id}', [App\Http\Controllers\Admin\DepartemenController::class, 'create'])->middleware('menu:perusahaan')->name('departemen.create');
        Route::post('/departemen/store', [App\Http\Controllers\Admin\DepartemenController::class, 'store'])->middleware('menu:perusahaan')->name('departemen.store');
        Route::delete('/departemen/destroy/{id}', [App\Http\Controllers\Admin\DepartemenController::class, 'destroy'])->middleware('menu:perusahaan')->name('departemen.destroy');
        // === END DEPARTEMEN ===

        // === DIVISI ===
        Route::get('/divisi/create/{perusahaan_id}', [App\Http\Controllers\Admin\DivisiController::class, 'create'])->middleware('menu:perusahaan')->name('divisi.create');
        Route::post('/divisi/store', [App\Http\Controllers\Admin\DivisiController::class, 'store'])->middleware('menu:perusahaan')->name('divisi.store');
        Route::delete('/divisi/destroy/{id}', [App\Http\Controllers\Admin\DivisiController::class, 'destroy'])->middleware('menu:perusahaan')->name('divisi.destroy');
        Route::put('/divisi/{id}', [App\Http\Controllers\Admin\DivisiController::class, 'update'])->middleware('menu:perusahaan')->name('divisi.update');
        Route::post('/divisi/merge', [App\Http\Controllers\Admin\DivisiController::class, 'mergeDivisi'])->middleware('menu:perusahaan')->name('divisi.merge');
        //=== END DIVISI ===

        Route::resource('/resign', 'App\Http\Controllers\Admin\ResignController')
            ->only(['index', 'store', 'edit', 'update', 'destroy'])
            ->middleware('menu:resign');
        Route::resource('/surat-peringatan', 'App\Http\Controllers\Admin\SuratPeringatanController')
            ->only(['index', 'store', 'edit', 'update', 'destroy'])
            ->middleware('menu:surat_peringatan');

        Route::resource('/setting-lokasi-presensi', 'App\Http\Controllers\Admin\SettingLokasiPresensiController')
            ->except(['show'])
            ->middleware('menu:setting_lokasi_presensi');
        Route::resource('/master-jadwal-kerja', WorkPatternController::class)
            ->except(['show'])
            ->middleware(['menu:jadwal_kerja', 'role:Super Admin,HR,HOD,Admin Divisi'])
            ->names('work-patterns');
        Route::resource('/master-shift', ShiftController::class)
            ->except(['show'])
            ->middleware(['menu:master_shift', 'role:Super Admin,HR,HOD,Admin Divisi'])
            ->names('shifts');
        Route::resource('/master-tanggal-merah', NationalHolidayController::class)
            ->only(['index', 'store', 'destroy'])
            ->middleware(['menu:master_tanggal_merah', 'role:Super Admin,HR'])
            ->parameters(['master-tanggal-merah' => 'nationalHoliday'])
            ->names('national-holidays');
        Route::post('/master-jadwal-kerja/bulk-assign', [WorkPatternController::class, 'bulkAssign'])
            ->middleware(['menu:jadwal_kerja', 'role:Super Admin,HR,HOD,Admin Divisi'])
            ->name('work-patterns.bulk-assign');
        Route::get('/perintah-lembur/employees/search', [AdminOvertimeOrderController::class, 'searchEmployees'])
            ->middleware(['menu:lembur', 'role:Super Admin,HR,HOD,Admin Divisi'])
            ->name('overtime-orders.employees.search');
        Route::get('/master-lembur', [OvertimeMasterController::class, 'index'])
            ->middleware(['menu:lembur', 'role:Super Admin,HR'])
            ->name('overtime-masters.index');
        Route::get('/master-lembur/create', [OvertimeMasterController::class, 'create'])
            ->middleware(['menu:lembur', 'role:Super Admin,HR'])
            ->name('overtime-masters.create');
        Route::post('/master-lembur', [OvertimeMasterController::class, 'store'])
            ->middleware(['menu:lembur', 'role:Super Admin,HR'])
            ->name('overtime-masters.store');
        Route::get('/master-lembur/{overtimePayRule}/edit', [OvertimeMasterController::class, 'edit'])
            ->middleware(['menu:lembur', 'role:Super Admin,HR'])
            ->name('overtime-masters.edit');
        Route::put('/master-lembur/{overtimePayRule}', [OvertimeMasterController::class, 'update'])
            ->middleware(['menu:lembur', 'role:Super Admin,HR'])
            ->name('overtime-masters.update');
        Route::post('/master-lembur/calculate', [OvertimeMasterController::class, 'calculate'])
            ->middleware(['menu:lembur', 'role:Super Admin,HR'])
            ->name('overtime-masters.calculate');
        Route::resource('/perintah-lembur', AdminOvertimeOrderController::class)
            ->except(['edit', 'update'])
            ->middleware(['menu:lembur', 'role:Super Admin,HR,HOD,Admin Divisi'])
            ->names('overtime-orders');

        // === ROLE ===
        Route::resource('/setting-role', '\App\Http\Controllers\Admin\SettingRoleController')
            ->except(['show'])
            ->middleware('menu:setting_role');
        Route::patch('/role/update/{id}', [SettingRoleController::class, 'updateRole'])->middleware('menu:setting_role')->name('role.update');
        // === END ROLE ===

        Route::get('/data-presensi/review-wajah', [PresensiAdminController::class, 'faceReview'])
            ->middleware(['menu:data_presensi', 'role:Super Admin,HR'])
            ->name('data-presensi.face-review.index');
        Route::get('/data-presensi/review-wajah/{verification}/selfie', [PresensiAdminController::class, 'faceReviewSelfie'])
            ->middleware(['menu:data_presensi', 'role:Super Admin,HR'])
            ->name('data-presensi.face-review.selfie');
        Route::post('/data-presensi/review-wajah/{verification}/decision', [PresensiAdminController::class, 'decideFaceReview'])
            ->middleware(['menu:data_presensi', 'role:Super Admin,HR'])
            ->name('data-presensi.face-review.decide');

        Route::resource('/data-presensi', 'App\Http\Controllers\Admin\PresensiController')
            ->only(['index'])
            ->middleware('menu:data_presensi');

        Route::get('/ajax/departemen-by-area', [App\Http\Controllers\Admin\KaryawanController::class, 'departemenByArea'])->middleware('menu:data_karyawan,setting_hari_off,data_presensi')->name('ajax.departemen.by.area');
        Route::get('/ajax/divisi-by-departemen', [App\Http\Controllers\Admin\KaryawanController::class, 'divisiByDepartemen'])->middleware('menu:data_karyawan,setting_hari_off,pengaturan_shift,data_presensi')->name('ajax.divisi.by.departemen');

        Route::get('fetch/data-presensi', [PresensiAdminController::class, 'dataPresensi'])->middleware('menu:data_presensi')->name('fetch.data-presensi');
        Route::get('/presensi/export', [PresensiAdminController::class, 'export'])->middleware('menu:data_presensi')->name('presensi.export');

        Route::prefix('third-party')->middleware('menu:exit_portal')->group(function () {
            Route::resource('/search-by-security', 'App\Http\Controllers\SearchBySecurity\UserController')
                ->except(['show']);
            Route::resource('/search-logs', 'App\Http\Controllers\SearchBySecurity\SearchLogController')
                ->only(['index']);
        });
    });

    Route::group(['prefix' => 'approval', 'middleware' => ['auth']], function () {

        Route::get('/hod/cuti', [CutiApprovalController::class, 'hodIndex'])->middleware('menu:approval_hod')->name('approval.cuti.hod');
        Route::post('/hod/cuti/{id}', [CutiApprovalController::class, 'hodProcess'])->middleware('menu:approval_hod')->name('approval.cuti.hod.process');

        Route::get('/hrd/cuti', [CutiApprovalController::class, 'hrdIndex'])->middleware('menu:approval_hr')->name('approval.cuti.hrd');
        Route::post('/hrd/cuti/{id}', [CutiApprovalController::class, 'hrdProcess'])->middleware('menu:approval_hr')->name('approval.cuti.hrd.process');

        Route::get('/hod/cuti-roster', [RosterApprovalController::class, 'hodIndex'])->middleware('menu:approval_hod')->name('approval.roster.hod');
        Route::post('/hod/cuti-roster/{id}', [RosterApprovalController::class, 'hodProcess'])->middleware('menu:approval_hod')->name('approval.roster.hod.process');
        Route::get('/hod/show/cuti-roster/{id}', [RosterApprovalController::class, 'hodShow'])->middleware('menu:approval_hod')->name('approval.roster.hod.show');
        Route::get('/hod/cuti-roster/{id}/attachment', [RosterApprovalController::class, 'hodAttachment'])->middleware('menu:approval_hod')->name('approval.roster.hod.attachment');
        Route::get('/hod/off-roster', [RosterOffApprovalController::class, 'hodIndex'])->middleware('menu:approval_hod')->name('approval.roster-off.hod');
        Route::post('/hod/off-roster/{id}', [RosterOffApprovalController::class, 'hodProcess'])->middleware('menu:approval_hod')->name('approval.roster-off.hod.process');

        Route::get('/hrd/cuti-roster', [RosterApprovalController::class, 'hrdIndex'])->middleware('menu:approval_hr')->name('approval.roster.hrd');
        Route::get('/hrd/show/cuti-roster/{id}', [RosterApprovalController::class, 'hrdShow'])->middleware('menu:approval_hr')->name('approval.roster.hrd.show');
        Route::get('/hrd/cuti-roster/{id}/attachment', [RosterApprovalController::class, 'hrdAttachment'])->middleware('menu:approval_hr')->name('approval.roster.hrd.attachment');
        Route::post('/hrd/cuti-roster/{id}', [RosterApprovalController::class, 'hrdProcess'])->middleware('menu:approval_hr')->name('approval.roster.hrd.process');
        Route::get('/hrd/off-roster', [RosterOffApprovalController::class, 'hrdIndex'])->middleware('menu:approval_hr')->name('approval.roster-off.hrd');
        Route::post('/hrd/off-roster/{id}', [RosterOffApprovalController::class, 'hrdProcess'])->middleware('menu:approval_hr')->name('approval.roster-off.hrd.process');

        Route::get('/hod/izin', [IzinApprovalController::class, 'hodIndex'])->middleware('menu:approval_hod')->name('approval.izin.hod');
        Route::post('/hod/izin/{id}', [IzinApprovalController::class, 'hodProcess'])->middleware('menu:approval_hod')->name('approval.izin.hod.process');

        Route::get('/hrd/izin', [IzinApprovalController::class, 'hrdIndex'])->middleware('menu:approval_hr')->name('approval.izin.hrd');
        Route::post('/hrd/izin/{id}', [IzinApprovalController::class, 'hrdProcess'])->middleware('menu:approval_hr')->name('approval.izin.hrd.process');
    });

    Route::group(['prefix' => 'admin-divisi', 'middleware' => ['auth', 'menu:setting_hari_off']], function () {

        Route::get('/set-kehadiran', [AttendanceSettingController::class, 'index'])->name('set-kehadiran.index');
        Route::post('/set-kehadiran/update', [AttendanceSettingController::class, 'update'])->name('set-kehadiran.update');
        Route::post('/set-kehadiran/bulk-upload-face-reference', [AttendanceSettingController::class, 'bulkUploadFaceReferences'])->name('set-kehadiran.bulk-upload-face-reference');
    });

    Route::group(['prefix' => 'admin-divisi', 'middleware' => ['auth', 'menu:pengaturan_shift', 'role:Super Admin,HR,HOD,Admin Divisi']], function () {
        Route::get('/set-shift', [ShiftSettingController::class, 'index'])->name('shift-settings.index');
        Route::post('/set-shift/update', [ShiftSettingController::class, 'update'])->name('shift-settings.update');
    });
});

Route::group(['prefix' => 'wilayah', 'middleware' => ['auth']], function () {
    Route::middleware('menu:distribusi_wilayah')->group(function () {
        Route::get('/distribusi/export', [App\Http\Controllers\Admin\WilayahController::class, 'export'])->name('distribusi.export');
        Route::get('/distribusi/export-excel', [App\Http\Controllers\Admin\WilayahController::class, 'exportExcel'])->name('distribusi.export-excel');
        Route::resource('/distribusi', 'App\Http\Controllers\Admin\WilayahController')
            ->only(['index']);
    });

    Route::middleware('menu:data_karyawan,distribusi_wilayah')->group(function () {
        Route::get('/provinces', [App\Http\Controllers\Admin\WilayahController::class, 'provinsi'])->name('wilayah.provinces');
        Route::get('/kabupatens/{provinceId}', [App\Http\Controllers\Admin\WilayahController::class, 'kabupaten'])->name('wilayah.kabupatens');
        Route::get('/kecamatans/{kabupatenId}', [App\Http\Controllers\Admin\WilayahController::class, 'kecamatan'])->name('wilayah.kecamatans');
        Route::get('/kelurahans/{kecamatanId}', [App\Http\Controllers\Admin\WilayahController::class, 'kelurahan'])->name('wilayah.kelurahans');
    });
});

Route::group(['prefix' => 'api/'], function () {
    route::get('/airports', [ApiController::class, 'getAirport']);
    Route::post('/gps-log', [PresensiController::class, 'logGps'])->middleware(['auth', 'menu:presensi', 'throttle:gps-log']);
});
