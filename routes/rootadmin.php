<?php

use App\Http\Controllers\RootAdmin\AuditLogController;
use App\Http\Controllers\RootAdmin\BillingSettingsController;
use App\Http\Controllers\RootAdmin\DashboardController;
use App\Http\Controllers\RootAdmin\SegmentController;
use App\Http\Controllers\RootAdmin\SubscriptionController;
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
    ->middleware(['auth', 'verified', 'can:platform-admin'])
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

        Route::get('assinaturas', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::get('assinaturas/{subscription}', [SubscriptionController::class, 'show'])->name('subscriptions.show');
        Route::post('tenants/{tenant}/assinatura', [SubscriptionController::class, 'start'])->name('subscriptions.start');
        Route::post('assinaturas/{subscription}/prorrogar', [SubscriptionController::class, 'extendTrial'])->name('subscriptions.extend');
        Route::patch('assinaturas/{subscription}/condicoes', [SubscriptionController::class, 'terms'])->name('subscriptions.terms');
        Route::post('assinaturas/{subscription}/cancelar', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
        Route::post('cobrancas/{charge}/baixa-manual', [SubscriptionController::class, 'settle'])->name('charges.settle');
        Route::post('cobrancas/{charge}/sincronizar', [SubscriptionController::class, 'sync'])->middleware('throttle:20,1')->name('charges.sync');

        Route::get('financeiro/configuracoes', [BillingSettingsController::class, 'edit'])->name('billing.edit');
        Route::patch('financeiro/plano', [BillingSettingsController::class, 'updatePlan'])->name('billing.plan');
        Route::patch('financeiro/efi', [BillingSettingsController::class, 'updateSettings'])->name('billing.settings');
        Route::post('financeiro/efi/testar', [BillingSettingsController::class, 'test'])->middleware('throttle:6,1')->name('billing.test');
        Route::post('financeiro/pacotes-ia', [BillingSettingsController::class, 'storePackage'])->name('billing.packages.store');
        Route::patch('financeiro/pacotes-ia/{package}', [BillingSettingsController::class, 'updatePackage'])->name('billing.packages.update');
        Route::post('creditos-ia/compras/{purchase}/confirmar', [SubscriptionController::class, 'confirmPurchase'])->name('credits.purchases.confirm');
        Route::post('creditos-ia/compras/{purchase}/cancelar', [SubscriptionController::class, 'cancelPurchase'])->name('credits.purchases.cancel');
        Route::post('creditos-ia/compras/{purchase}/estornar', [SubscriptionController::class, 'refundPurchase'])->name('credits.purchases.refund');
    });
