<?php

use App\Http\Controllers\Admin\ApiController;
use App\Http\Controllers\Admin\SettingRoleController;
use App\Http\Controllers\Admin\SlipGajiController;
use App\Http\Controllers\Approval\CutiApprovalController;
use App\Http\Controllers\Approval\CutiRosterApprovalController;
use App\Http\Controllers\Approval\IzinApprovalController;
use App\Http\Controllers\Approval\RosterApprovalController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\IzinController;
use App\Http\Controllers\User\PresensiController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::group(['prefix' => '/', 'middleware' => ['auth', 'role:User,Administrator']], function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.karyawan');
    Route::resource('/cuti', 'App\Http\Controllers\User\CutiController');
    Route::resource('/presensi', 'App\Http\Controllers\User\PresensiController')->except('store');
    Route::post('/absen/{type}', [PresensiController::class, 'store'])->middleware('auth');
    Route::resource('/izin', 'App\Http\Controllers\User\IzinController');
    Route::resource('/roster', 'App\Http\Controllers\User\RosterController');
});

Route::group(['prefix' => 'admin', 'middleware' => ['auth', 'role:Administrator']], function () {

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

    Route::get('/ajax/departemen-by-area', [App\Http\Controllers\Admin\KaryawanController::class, 'departemenByArea'])->name('ajax.departemen.by.area');
    Route::get('/ajax/divisi-by-departemen', [App\Http\Controllers\Admin\KaryawanController::class, 'divisiByDepartemen'])->name('ajax.divisi.by.departemen');
});

Route::group(['prefix' => 'approval', 'middleware' => ['auth', 'role:Administrator, HOD, HRD']], function () {

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

Route::group(['prefix' => 'wilayah'], function () {
    Route::get('/provinces', [App\Http\Controllers\Admin\WilayahController::class, 'provinsi'])->name('wilayah.provinces');
    Route::get('/kabupatens/{provinceId}', [App\Http\Controllers\Admin\WilayahController::class, 'kabupaten'])->name('wilayah.kabupatens');
    Route::get('/kecamatans/{kabupatenId}', [App\Http\Controllers\Admin\WilayahController::class, 'kecamatan'])->name('wilayah.kecamatans');
    Route::get('/kelurahans/{kecamatanId}', [App\Http\Controllers\Admin\WilayahController::class, 'kelurahan'])->name('wilayah.kelurahans');
});

Route::group(['prefix' => 'api/'], function () {
    route::get('airports', [ApiController::class, 'getAirport']);
});
