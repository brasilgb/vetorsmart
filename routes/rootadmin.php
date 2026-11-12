<?php

use App\Http\Controllers\RootAdmin\AuditLogController;
use App\Http\Controllers\RootAdmin\DashboardController;
use App\Http\Controllers\RootAdmin\SegmentController;
use App\Http\Controllers\RootAdmin\TenantController;
use App\Http\Controllers\RootAdmin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RootAdmin console
|--------------------------------------------------------------------------
|
| Platform-level area. Protected by the `platform-admin` gate, which is separate
| from tenant roles and never grants access to tenant operational data.
| There is intentionally no delete route for companies or segments.
|
*/

Route::prefix('rootadmin')
    ->name('rootadmin.')
    ->middleware(['auth', 'rootadmin.mfa', 'can:platform-admin'])
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
        Route::post('tenants/{tenant}/status', [TenantController::class, 'updateStatus'])->name('tenants.status');

        Route::get('segments', [SegmentController::class, 'index'])->name('segments.index');
        Route::post('segments', [SegmentController::class, 'store'])->name('segments.store');
        Route::patch('segments/{segment}', [SegmentController::class, 'update'])->name('segments.update');

        Route::get('users', [UserController::class, 'index'])->name('users.index');

        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
    });
