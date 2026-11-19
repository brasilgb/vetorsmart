<?php

namespace App\Http\Controllers\RootAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BillingSetting;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\BillingEnforcement;
use App\Services\Billing\PlanCatalog;
use App\Support\PlatformTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

/**
 * Configurações Globais: the parameters in force, read-only. Every item carries an explicit state:
 * "configured" (set and in use), "disabled" (deliberately off) or "unavailable" (missing, or not readable here).
 * Secrets are never sent to the screen: for credentials only their presence is reported. Nothing here changes the
 * environment; .env stays on the server.
 */
class GlobalSettingsController extends Controller
{
    public function __invoke(PlanCatalog $catalog, BillingEnforcement $enforcement): Response
    {
        $billing = BillingSetting::current();
        $plan = Plan::main();
        $offer = $catalog->offer($plan);

        return Inertia::render('rootadmin/settings/index', [
            'sections' => [
                [
                    'title' => 'Plataforma',
                    'items' => [
                        $this->item('Ambiente', (string) config('app.env'), app()->environment('production') ? 'configured' : 'disabled', app()->environment('production') ? null : 'Fora de produção.'),
                        $this->item('Nome da aplicação', (string) config('app.name'), 'configured'),
                        $this->item('URL pública', (string) config('platform.public_url'), filled(config('platform.public_url')) ? 'configured' : 'unavailable'),
                        $this->item('URL da aplicação (links de e-mail)', (string) config('app.url'), filled(config('app.url')) ? 'configured' : 'unavailable'),
                        $this->item('Modo de depuração', config('app.debug') ? 'Ligado' : 'Desligado', config('app.debug') ? 'configured' : 'disabled', config('app.debug') ? 'Deve ficar desligado em produção.' : null),
                        $this->item('Estado operacional', app()->isDownForMaintenance() ? 'Em manutenção' : 'Em operação', app()->isDownForMaintenance() ? 'disabled' : 'configured'),
                    ],
                ],
                [
                    'title' => 'Assinaturas',
                    'items' => [
                        $this->item('Plano vigente', $plan->name, 'configured', $plan->pricesApproved() ? 'Valores aprovados.' : 'Valores provisórios, não aprovados.'),
                        $this->item('Ciclos disponíveis', $offer['cycles'] === [] ? 'Nenhum com valor' : implode(', ', array_map(fn (array $cycle): string => $cycle['label'], $offer['cycles'])), $offer['cycles'] === [] ? 'unavailable' : 'configured'),
                        $this->item('Período de teste', "{$plan->trial_days} dias", 'configured'),
                        $this->item('Usuários incluídos', $plan->included_users === null ? 'Sem limite' : (string) $plan->included_users, $plan->included_users === null ? 'disabled' : 'configured'),
                        $this->item('Franquia mensal de IA', $plan->ai_monthly_credits === null ? 'Não definida' : "{$plan->ai_monthly_credits} créditos", $plan->ai_monthly_credits === null ? 'disabled' : 'configured'),
                        $this->item('Cobrança em vigor', $enforcement->active() ? 'Em vigor desde '.PlatformTime::dateTime($billing->enforcement_starts_at) : 'Desligada', $enforcement->active() ? 'configured' : 'disabled', $enforcement->active() ? null : 'Uso liberado, sem cobrança.'),
                    ],
                ],
                [
                    'title' => 'Integrações',
                    'items' => [
                        $this->item('Efí: ambiente', $billing->isProduction() ? 'Produção' : 'Homologação', $billing->isProduction() ? 'configured' : 'disabled'),
                        $this->item('Efí: API Pix', $billing->pixConfigured() ? 'Credenciais informadas' : 'Credenciais ausentes', $billing->pixConfigured() ? 'configured' : 'unavailable'),
                        $this->item('Efí: API Cobranças', $billing->chargesConfigured() ? 'Credenciais informadas' : 'Credenciais ausentes', $billing->chargesConfigured() ? 'configured' : 'unavailable', $billing->connection_tested_at === null ? 'Conexão nunca testada.' : 'Conexão testada em '.PlatformTime::dateTime($billing->connection_tested_at).'.'),
                        $this->mailItem(),
                        $this->queueItem(),
                        $this->item('Provedor de IA', config('assistant.provider') === 'none' ? 'Nenhum (fluxo determinístico)' : (string) config('assistant.provider'), config('assistant.provider') === 'none' ? 'disabled' : (filled(config('assistant.provider_api_key')) ? 'configured' : 'unavailable')),
                        $this->item('Compra de créditos de IA', $billing->ai_credit_purchases_enabled ? 'Liberada' : 'Desligada', $billing->ai_credit_purchases_enabled ? 'configured' : 'disabled'),
                    ],
                ],
                [
                    'title' => 'Sites e Domínios',
                    'items' => [
                        $this->item('Domínio principal', (string) config('platform.central_host'), filled(config('platform.central_host')) ? 'configured' : 'unavailable'),
                        $this->item('Base dos subdomínios', (string) config('platform.tenant_base_domain'), filled(config('platform.tenant_base_domain')) ? 'configured' : 'unavailable'),
                        $this->item('Sites por subdomínio', config('platform.subdomain_sites') ? 'Ligados' : 'Desligados', config('platform.subdomain_sites') ? 'configured' : 'disabled', config('platform.subdomain_sites') ? null : 'Os sites respondem no endereço padrão /segmento/empresa.'),
                        $this->item('Domínios próprios por empresa', (string) config('platform.max_domains_per_tenant'), 'configured'),
                        $this->item('Trocas de subdomínio permitidas', (string) config('platform.max_subdomain_changes'), 'configured'),
                    ],
                ],
                [
                    'title' => 'Segurança',
                    'items' => [
                        $this->item('Confirmação de e-mail', Features::enabled(Features::emailVerification()) ? 'Exigida' : 'Desligada', Features::enabled(Features::emailVerification()) ? 'configured' : 'disabled'),
                        $this->item('Acesso legado sem confirmação', User::query()->whereNull('email_verified_at')->whereNotNull('email_verification_legacy_at')->count().' conta(s)', 'configured', filled(config('platform.legacy_email_grace_until')) ? 'Prazo: '.config('platform.legacy_email_grace_until').'.' : 'Sem prazo definido.'),
                        $this->item('Sessão', config('session.driver').' · '.config('session.lifetime').' min', 'configured', 'Cookie seguro: '.(config('session.secure') ? 'sim' : 'não').' · SameSite: '.(config('session.same_site') ?? 'padrão').'.'),
                        $this->item('Limites de tentativa', 'Login 5/min · confirmação 3/min e 10/h · atendimento público '.config('assistant.public_requests_per_minute').'/min', 'configured'),
                        $this->item('Auditoria administrativa', AuditLog::query()->where('created_at', '>=', now()->subDays(30))->count().' registro(s) em 30 dias', 'configured'),
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return array{label: string, value: string, state: string, note: string|null}
     */
    private function item(string $label, string $value, string $state, ?string $note = null): array
    {
        return ['label' => $label, 'value' => $value, 'state' => $state, 'note' => $note];
    }

    /**
     * @return array{label: string, value: string, state: string, note: string|null}
     */
    private function mailItem(): array
    {
        $mailer = (string) config('mail.default');
        $sends = ! in_array($mailer, ['log', 'array'], true);
        $hostSet = $mailer !== 'smtp' || filled(config('mail.mailers.smtp.host'));

        return $this->item(
            'Envio de e-mails',
            $mailer.($mailer === 'smtp' && $hostSet ? ' · '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port') : ''),
            $sends && $hostSet ? 'configured' : ($sends ? 'unavailable' : 'disabled'),
            $sends ? 'Remetente: '.config('mail.from.address').'. Entrega não verificada por esta tela.' : 'As mensagens só são registradas em log, não enviadas.',
        );
    }

    /**
     * @return array{label: string, value: string, state: string, note: string|null}
     */
    private function queueItem(): array
    {
        $connection = (string) config('queue.default');

        if ($connection === 'sync') {
            return $this->item('Processamento por filas', 'sync', 'disabled', 'Sem fila: os e-mails são enviados na própria requisição.');
        }

        if ($connection !== 'database' || ! Schema::hasTable('jobs')) {
            return $this->item('Processamento por filas', $connection, 'configured', 'Fila externa: pendências não lidas por esta tela.');
        }

        $pending = DB::table('jobs')->count();
        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

        return $this->item('Processamento por filas', "{$connection} · {$pending} pendente(s) · {$failed} com falha", 'configured', $failed > 0 ? 'Há tarefas com falha para revisar.' : 'A saúde do worker não é verificada por esta tela.');
    }
}
