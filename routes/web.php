<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Site\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');

// Public SEO files for each host. A static public/robots.txt takes precedence over this route,
// so that file must be retired before per-host robots rules take effect (see the report).
Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('site.sitemap');
Route::get('/robots.txt', [SiteController::class, 'robots'])->name('site.robots');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->middleware('tenant.resolve')
        ->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/tenant.php';
require __DIR__.'/rootadmin.php';
