<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Site\AssistantController;
use App\Http\Controllers\Site\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');

// Public SEO files for each host. A static public/robots.txt takes precedence over this route,
// so that file must be retired before per-host robots rules take effect (see the report).
Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('site.sitemap');
Route::get('/robots.txt', [SiteController::class, 'robots'])->name('site.robots');

// Visitor assistant of a company's published site. Hostname-restricted by SiteHostGuard; rate limited.
Route::get('/atendimento', [AssistantController::class, 'show'])->name('site.assistant.show');
Route::post('/atendimento/conversa', [AssistantController::class, 'start'])->middleware('throttle:assistant-public')->name('site.assistant.start');
Route::get('/atendimento/mensagens', [AssistantController::class, 'messages'])->middleware('throttle:assistant-public')->name('site.assistant.messages');
Route::post('/atendimento/mensagens', [AssistantController::class, 'send'])->middleware('throttle:assistant-public')->name('site.assistant.send');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->middleware('tenant.resolve')
        ->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/tenant.php';
require __DIR__.'/rootadmin.php';
