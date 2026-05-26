<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MobileAppVersionController;
use App\Http\Controllers\Api\VhireContractSignatureController;
use App\Http\Controllers\Api\VhireOnboardingCandidateController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/mobile/app-version', [MobileAppVersionController::class, 'check'])
    ->withoutMiddleware('throttle:api')
    ->middleware('throttle:mobile-app-version')
    ->name('api.mobile.app-version');

Route::prefix('hris')
    ->middleware('vhire.token')
    ->group(function () {
        Route::post('/onboarding-candidates', [VhireOnboardingCandidateController::class, 'store'])
            ->name('api.hris.onboarding-candidates.store');
        Route::post('/contracts/{contract}/signature-status', [VhireContractSignatureController::class, 'store'])
            ->name('api.hris.contracts.signature-status');
    });
