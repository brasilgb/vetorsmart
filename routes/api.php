<?php

use App\Http\Controllers\Api\V1\Admin\SegmentController as AdminSegmentController;
use App\Http\Controllers\Api\V1\Admin\TenantController as AdminTenantController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Catalog\ServiceCategoryController;
use App\Http\Controllers\Api\V1\Catalog\ServiceController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\Professionals\ProfessionalAvailabilityController;
use App\Http\Controllers\Api\V1\Professionals\ProfessionalController;
use App\Http\Controllers\Api\V1\TenantSettingsController;
use App\Http\Controllers\Api\V1\TenantUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Authentication reuses the session guard of the starter kit (web group), so
| the API accepts the same login as the web application. The active tenant is
| resolved server-side by the tenant.resolve middleware. No route accepts a
| tenant_id from the client.
|
*/

Route::prefix('v1')->middleware(['web', 'auth'])->group(function () {
    Route::get('auth/user', [AuthController::class, 'user'])->name('api.v1.auth.user');
    Route::get('auth/tenants', [AuthController::class, 'tenants'])->name('api.v1.auth.tenants');
    Route::post('auth/switch-tenant', [AuthController::class, 'switchTenant'])->name('api.v1.auth.switch-tenant');
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');

    Route::prefix('tenant')->middleware('tenant.resolve')->group(function () {
        Route::get('settings', [TenantSettingsController::class, 'show'])->name('api.v1.tenant.settings.show');
        Route::patch('settings', [TenantSettingsController::class, 'update'])->name('api.v1.tenant.settings.update');

        Route::get('users', [TenantUserController::class, 'index'])->name('api.v1.tenant.users.index');
        Route::post('users', [TenantUserController::class, 'store'])->name('api.v1.tenant.users.store');
        Route::patch('users/{membershipId}', [TenantUserController::class, 'update'])
            ->whereNumber('membershipId')
            ->name('api.v1.tenant.users.update');
        Route::post('users/{membershipId}/activate', [TenantUserController::class, 'activate'])
            ->whereNumber('membershipId')
            ->name('api.v1.tenant.users.activate');
        Route::delete('users/{membershipId}', [TenantUserController::class, 'destroy'])
            ->whereNumber('membershipId')
            ->name('api.v1.tenant.users.destroy');

        Route::apiResource('customers', CustomerController::class)
            ->whereNumber('customer')
            ->names('api.v1.tenant.customers');

        Route::apiResource('service-categories', ServiceCategoryController::class)
            ->whereNumber('category')
            ->parameters(['service-categories' => 'category'])
            ->names('api.v1.tenant.service-categories');

        Route::apiResource('services', ServiceController::class)
            ->whereNumber('service')
            ->names('api.v1.tenant.services');

        Route::apiResource('professionals', ProfessionalController::class)
            ->whereNumber('professional')
            ->names('api.v1.tenant.professionals');
        Route::post('professionals/{professional}/activate', [ProfessionalController::class, 'activate'])
            ->whereNumber('professional')
            ->name('api.v1.tenant.professionals.activate');
        Route::get('professionals/{professional}/services', [ProfessionalController::class, 'services'])
            ->whereNumber('professional')
            ->name('api.v1.tenant.professionals.services.index');
        Route::get('professionals/{professional}/availability', [ProfessionalAvailabilityController::class, 'index'])
            ->whereNumber('professional')
            ->name('api.v1.tenant.professionals.availability.index');
        Route::put('professionals/{professional}/availability', [ProfessionalAvailabilityController::class, 'sync'])
            ->whereNumber('professional')
            ->name('api.v1.tenant.professionals.availability.sync');
        Route::put('professionals/{professional}/services', [ProfessionalController::class, 'syncServices'])
            ->whereNumber('professional')
            ->name('api.v1.tenant.professionals.services.sync');
    });

    Route::prefix('admin')->middleware('can:platform-admin')->group(function () {
        Route::get('tenants', [AdminTenantController::class, 'index'])->name('api.v1.admin.tenants.index');
        Route::get('tenants/{tenant}', [AdminTenantController::class, 'show'])->name('api.v1.admin.tenants.show');
        Route::post('tenants/{tenant}/activate', [AdminTenantController::class, 'activate'])->name('api.v1.admin.tenants.activate');
        Route::post('tenants/{tenant}/suspend', [AdminTenantController::class, 'suspend'])->name('api.v1.admin.tenants.suspend');

        Route::get('segments', [AdminSegmentController::class, 'index'])->name('api.v1.admin.segments.index');
        Route::post('segments', [AdminSegmentController::class, 'store'])->name('api.v1.admin.segments.store');
        Route::patch('segments/{segment}', [AdminSegmentController::class, 'update'])->name('api.v1.admin.segments.update');
    });
});
