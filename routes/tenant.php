<?php

use App\Http\Controllers\Tenant\AgendaController;
use App\Http\Controllers\Tenant\AssistantController;
use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\Tenant\GrowthController;
use App\Http\Controllers\Tenant\NotificationController;
use App\Http\Controllers\Tenant\ProfessionalController;
use App\Http\Controllers\Tenant\ServiceController;
use App\Http\Controllers\Tenant\ServiceRequestController;
use App\Http\Controllers\Tenant\SettingsController;
use App\Http\Controllers\Tenant\SubscriptionController;
use App\Http\Controllers\Tenant\SupportController;
use App\Http\Controllers\Tenant\TeamController;
use App\Http\Controllers\Tenant\WebsiteController;
use App\Http\Controllers\Tenant\WebsiteDomainController;
use App\Http\Controllers\Tenant\WebsiteSubdomainController;
use App\Services\Websites\WebsiteMedia;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant management screens
|--------------------------------------------------------------------------
|
| Operational screens of the active company. Each route runs the tenant.resolve
| middleware, and every controller reads the company from TenantContext, never
| from the URL or the payload. Records are removed by archiving, not deleting.
|
*/

Route::middleware(['auth', 'verified', 'tenant.resolve', 'subscription.access'])->group(function () {
    Route::get('clientes', [CustomerController::class, 'index'])->name('tenant.customers.index');
    Route::get('clientes/novo', [CustomerController::class, 'create'])->name('tenant.customers.create');
    Route::post('clientes', [CustomerController::class, 'store'])->name('tenant.customers.store');
    Route::get('clientes/{customer}/editar', [CustomerController::class, 'edit'])->whereNumber('customer')->name('tenant.customers.edit');
    Route::patch('clientes/{customer}', [CustomerController::class, 'update'])->whereNumber('customer')->name('tenant.customers.update');
    Route::delete('clientes/{customer}', [CustomerController::class, 'archive'])->whereNumber('customer')->name('tenant.customers.archive');

    Route::get('solicitacoes', [ServiceRequestController::class, 'index'])->name('tenant.requests.index');
    Route::get('solicitacoes/nova', [ServiceRequestController::class, 'create'])->name('tenant.requests.create');
    Route::post('solicitacoes', [ServiceRequestController::class, 'store'])->name('tenant.requests.store');
    Route::get('solicitacoes/{serviceRequest}', [ServiceRequestController::class, 'show'])->whereNumber('serviceRequest')->name('tenant.requests.show');
    Route::patch('solicitacoes/{serviceRequest}/triagem', [ServiceRequestController::class, 'triage'])->whereNumber('serviceRequest')->name('tenant.requests.triage');
    Route::post('solicitacoes/{serviceRequest}/agendar', [ServiceRequestController::class, 'schedule'])->whereNumber('serviceRequest')->name('tenant.requests.schedule');
    Route::post('solicitacoes/{serviceRequest}/iniciar', [ServiceRequestController::class, 'start'])->whereNumber('serviceRequest')->name('tenant.requests.start');
    Route::post('solicitacoes/{serviceRequest}/concluir', [ServiceRequestController::class, 'complete'])->whereNumber('serviceRequest')->name('tenant.requests.complete');
    Route::post('solicitacoes/{serviceRequest}/nao-realizada', [ServiceRequestController::class, 'notPerformed'])->whereNumber('serviceRequest')->name('tenant.requests.not-performed');
    Route::post('solicitacoes/{serviceRequest}/cancelar', [ServiceRequestController::class, 'cancel'])->whereNumber('serviceRequest')->name('tenant.requests.cancel');

    Route::get('agenda', [AgendaController::class, 'index'])->name('tenant.agenda.index');
    Route::get('agenda/disponibilidade', [AgendaController::class, 'slots'])->name('tenant.agenda.slots');
    Route::patch('agenda/agendamentos/{appointment}', [AgendaController::class, 'reschedule'])->whereNumber('appointment')->name('tenant.agenda.reschedule');
    Route::post('agenda/agendamentos/{appointment}/cancelar', [AgendaController::class, 'cancel'])->whereNumber('appointment')->name('tenant.agenda.cancel');
    Route::post('agenda/bloqueios', [AgendaController::class, 'storeBlock'])->name('tenant.agenda.blocks.store');
    Route::delete('agenda/bloqueios/{block}', [AgendaController::class, 'destroyBlock'])->whereNumber('block')->name('tenant.agenda.blocks.destroy');

    Route::get('servicos', [ServiceController::class, 'index'])->name('tenant.services.index');
    Route::get('servicos/novo', [ServiceController::class, 'create'])->name('tenant.services.create');
    Route::post('servicos', [ServiceController::class, 'store'])->name('tenant.services.store');
    Route::get('servicos/{service}/editar', [ServiceController::class, 'edit'])->whereNumber('service')->name('tenant.services.edit');
    Route::patch('servicos/{service}', [ServiceController::class, 'update'])->whereNumber('service')->name('tenant.services.update');
    Route::delete('servicos/{service}', [ServiceController::class, 'deactivate'])->whereNumber('service')->name('tenant.services.deactivate');
    Route::put('servicos/{service}/profissionais', [ServiceController::class, 'syncProfessionals'])->whereNumber('service')->name('tenant.services.professionals');

    Route::get('profissionais', [ProfessionalController::class, 'index'])->name('tenant.professionals.index');
    Route::get('profissionais/novo', [ProfessionalController::class, 'create'])->name('tenant.professionals.create');
    Route::post('profissionais', [ProfessionalController::class, 'store'])->name('tenant.professionals.store');
    Route::get('profissionais/{professional}/editar', [ProfessionalController::class, 'edit'])->whereNumber('professional')->name('tenant.professionals.edit');
    Route::patch('profissionais/{professional}', [ProfessionalController::class, 'update'])->whereNumber('professional')->name('tenant.professionals.update');
    Route::delete('profissionais/{professional}', [ProfessionalController::class, 'deactivate'])->whereNumber('professional')->name('tenant.professionals.deactivate');
    Route::post('profissionais/{professional}/ativar', [ProfessionalController::class, 'activate'])->whereNumber('professional')->name('tenant.professionals.activate');

    Route::get('equipe', [TeamController::class, 'index'])->name('tenant.team.index');
    Route::post('equipe', [TeamController::class, 'store'])->middleware('verified.strict')->name('tenant.team.store');
    Route::patch('equipe/{membership}', [TeamController::class, 'update'])->whereNumber('membership')->middleware('verified.strict')->name('tenant.team.update');
    Route::post('equipe/{membership}/ativar', [TeamController::class, 'activate'])->whereNumber('membership')->middleware('verified.strict')->name('tenant.team.activate');
    Route::delete('equipe/{membership}', [TeamController::class, 'deactivate'])->whereNumber('membership')->middleware('verified.strict')->name('tenant.team.deactivate');

    Route::get('configuracoes', [SettingsController::class, 'show'])->name('tenant.settings.show');
    Route::patch('configuracoes', [SettingsController::class, 'update'])->name('tenant.settings.update');

    Route::get('meu-site', [WebsiteController::class, 'index'])->name('tenant.website.index');
    Route::patch('meu-site/rascunho', [WebsiteController::class, 'saveDraft'])->name('tenant.website.draft');
    Route::post('meu-site/publicar', [WebsiteController::class, 'publish'])->name('tenant.website.publish');
    Route::post('meu-site/retirar', [WebsiteController::class, 'unpublish'])->name('tenant.website.unpublish');
    Route::get('meu-site/previa', [WebsiteController::class, 'preview'])->name('tenant.website.preview');
    Route::get('meu-site/previa/midia/{file}', [WebsiteController::class, 'previewMedia'])->where('file', WebsiteMedia::FILE_PATTERN)->name('tenant.website.preview.media');
    Route::post('meu-site/imagens/{kind}', [WebsiteController::class, 'storeImage'])->whereIn('kind', ['logo', 'hero'])->middleware('throttle:20,1')->name('tenant.website.images.store');
    Route::delete('meu-site/imagens/{kind}', [WebsiteController::class, 'removeImage'])->whereIn('kind', ['logo', 'hero'])->name('tenant.website.images.destroy');
    Route::patch('meu-site/subdominio', [WebsiteSubdomainController::class, 'update'])->middleware('throttle:10,1')->name('tenant.website.subdomain.update');
    Route::post('meu-site/dominios', [WebsiteDomainController::class, 'store'])->name('tenant.website.domains.store');
    Route::post('meu-site/dominios/{domain}/verificar', [WebsiteDomainController::class, 'verify'])->whereNumber('domain')->name('tenant.website.domains.verify');
    Route::post('meu-site/dominios/{domain}/principal', [WebsiteDomainController::class, 'primary'])->whereNumber('domain')->name('tenant.website.domains.primary');
    Route::delete('meu-site/dominios/{domain}', [WebsiteDomainController::class, 'destroy'])->whereNumber('domain')->name('tenant.website.domains.destroy');

    Route::get('meu-crescimento', GrowthController::class)->name('tenant.growth.index');

    Route::get('notificacoes', [NotificationController::class, 'index'])->name('tenant.notifications.index');
    Route::post('notificacoes/lidas', [NotificationController::class, 'readAll'])->name('tenant.notifications.read-all');
    Route::patch('notificacoes/preferencias', [NotificationController::class, 'preferences'])->name('tenant.notifications.preferences');
    Route::post('notificacoes/{notification}/abrir', [NotificationController::class, 'open'])->whereNumber('notification')->name('tenant.notifications.open');
    Route::post('notificacoes/{notification}/lida', [NotificationController::class, 'read'])->whereNumber('notification')->name('tenant.notifications.read');

    Route::get('atendimento-ia', [AssistantController::class, 'index'])->name('tenant.assistant.index');
    Route::patch('atendimento-ia/configuracoes', [AssistantController::class, 'update'])->name('tenant.assistant.update');
    Route::post('atendimento-ia/conversas/{conversation}/assumir', [AssistantController::class, 'assume'])->whereNumber('conversation')->name('tenant.assistant.assume');
    Route::post('atendimento-ia/conversas/{conversation}/devolver', [AssistantController::class, 'returnToAi'])->whereNumber('conversation')->name('tenant.assistant.return');
    Route::post('atendimento-ia/conversas/{conversation}/encerrar', [AssistantController::class, 'close'])->whereNumber('conversation')->name('tenant.assistant.close');
    Route::post('atendimento-ia/conversas/{conversation}/identificar', [AssistantController::class, 'identify'])->whereNumber('conversation')->name('tenant.assistant.identify');
    Route::post('atendimento-ia/conversas/{conversation}/responder', [AssistantController::class, 'reply'])->whereNumber('conversation')->name('tenant.assistant.reply');
});

/*
| Minha Assinatura. Outside the subscription.access middleware on purpose: a company whose subscription is suspended
| or cancelled must still reach this page to pay or contract again.
*/
Route::middleware(['auth', 'verified', 'tenant.resolve'])->group(function () {
    Route::get('assinatura', [SubscriptionController::class, 'show'])->name('tenant.subscription.show');

    Route::get('suporte', [SupportController::class, 'index'])->name('tenant.support.index');
    Route::post('suporte', [SupportController::class, 'store'])->middleware('throttle:10,1')->name('tenant.support.store');
    Route::get('suporte/{ticket}', [SupportController::class, 'show'])->whereNumber('ticket')->name('tenant.support.show');
    Route::post('suporte/{ticket}/respostas', [SupportController::class, 'reply'])->whereNumber('ticket')->middleware('throttle:30,1')->name('tenant.support.reply');
    Route::post('assinatura/contratar', [SubscriptionController::class, 'contract'])->middleware('verified.strict')->middleware('throttle:10,1')->name('tenant.subscription.contract');
    Route::post('assinatura/ciclo', [SubscriptionController::class, 'interval'])->middleware('verified.strict')->middleware('throttle:10,1')->name('tenant.subscription.interval');
    Route::post('assinatura/vagas', [SubscriptionController::class, 'seats'])->middleware('verified.strict')->middleware('throttle:10,1')->name('tenant.subscription.seats');
    Route::post('assinatura/creditos-ia', [SubscriptionController::class, 'buyCredits'])->middleware('verified.strict')->middleware('throttle:10,1')->name('tenant.subscription.credits');
    Route::post('assinatura/cancelar', [SubscriptionController::class, 'cancel'])->middleware('verified.strict')->name('tenant.subscription.cancel');
    Route::post('assinatura/retomar', [SubscriptionController::class, 'resume'])->middleware('verified.strict')->name('tenant.subscription.resume');
});
