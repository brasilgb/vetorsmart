<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Site\AssistantController;
use App\Http\Controllers\Site\SiteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Company hostnames
|--------------------------------------------------------------------------
|
| Any host other than the central one: a company's own verified domain, which serves its site, or an earlier platform
| subdomain, which redirects to the standard address. Unknown hosts are refused by TrustedHosts before routing, and
| SiteHostGuard answers 404 for any other path on these hosts. Registered first so they win over the central routes.
|
*/

Route::domain('{siteHost}')
    ->where(['siteHost' => '(?!'.preg_quote((string) config('platform.central_host'), '#').'$)[^/]+'])
    ->group(function () {
        Route::get('/', [SiteController::class, 'hostPage'])->name('site.host.page');
        Route::get('/servicos', [SiteController::class, 'hostServices'])->name('site.host.services');
        Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('site.host.sitemap');
        Route::get('/robots.txt', [SiteController::class, 'robots'])->name('site.host.robots');
        Route::get('/atendimento', [AssistantController::class, 'show'])->name('site.host.assistant.show');
        Route::post('/atendimento/conversa', [AssistantController::class, 'start'])->middleware('throttle:assistant-public')->name('site.host.assistant.start');
        Route::get('/atendimento/mensagens', [AssistantController::class, 'messages'])->middleware('throttle:assistant-public')->name('site.host.assistant.messages');
        Route::post('/atendimento/mensagens', [AssistantController::class, 'send'])->middleware('throttle:assistant-public')->name('site.host.assistant.send');
    });

Route::get('/', LandingController::class)->name('home');

// Central sitemap and robots rules. A static public/robots.txt would take precedence over this route.
Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('site.sitemap');
Route::get('/robots.txt', [SiteController::class, 'robots'])->name('site.robots');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->middleware('tenant.resolve')
        ->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/tenant.php';
require __DIR__.'/rootadmin.php';

/*
|--------------------------------------------------------------------------
| Public company sites: /{segment}/{company}
|--------------------------------------------------------------------------
|
| The standard address of every company site, on the main domain. Registered last, so every platform route wins
| over them. A segment never receives a slug that is the first segment of a platform route (ReservedPaths), and the
| resolver refuses one as well. The company comes from these two slugs only. The visitor assistant endpoints are
| rate limited per visitor and per company.
|
*/

Route::prefix('{segment}/{company}')
    ->where(['segment' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'company' => '[a-z0-9]+(?:-[a-z0-9]+)*'])
    ->group(function () {
        Route::get('/', [SiteController::class, 'page'])->name('site.page');
        Route::get('servicos', [SiteController::class, 'services'])->name('site.services');
        Route::get('atendimento', [AssistantController::class, 'show'])->name('site.assistant.show');
        Route::post('atendimento/conversa', [AssistantController::class, 'start'])->middleware('throttle:assistant-public')->name('site.assistant.start');
        Route::get('atendimento/mensagens', [AssistantController::class, 'messages'])->middleware('throttle:assistant-public')->name('site.assistant.messages');
        Route::post('atendimento/mensagens', [AssistantController::class, 'send'])->middleware('throttle:assistant-public')->name('site.assistant.send');
    });
