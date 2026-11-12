<?php

namespace App\Http\Controllers\RootAdmin;

use App\Enums\AppointmentStatus;
use App\Enums\WebsiteStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AssistantUsage;
use App\Models\Tenant;
use App\Models\TenantWebsite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Platform indicators computed with grouped queries. Lists are never loaded to count them.
     *
     * Indicators that depend on modules not implemented yet are returned as `pending`, so the
     * console states they are unavailable instead of showing invented numbers.
     */
    public function __invoke(): Response
    {
        $byStatus = Tenant::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $distribution = DB::table('tenants')
            ->leftJoin('segments', 'segments.id', '=', 'tenants.segment_id')
            ->selectRaw("COALESCE(segments.name, 'Sem segmento') as segment_name, COUNT(tenants.id) as total")
            ->groupBy('segment_name')
            ->orderByDesc('total')
            ->get();

        $recent = Tenant::query()
            ->with('segment:id,name')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'name', 'slug', 'status', 'segment_id', 'created_at'])
            ->map(fn (Tenant $tenant): array => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'segment' => $tenant->segment?->name,
                'status' => $tenant->status->value,
                'created_at' => $tenant->created_at?->toDateString(),
            ]);

        $monthStart = now()->startOfMonth()->toDateString();

        $aiUsage = AssistantUsage::query()
            ->where('usage_date', '>=', $monthStart)
            ->selectRaw('COALESCE(SUM(messages), 0) as messages, COALESCE(SUM(provider_calls), 0) as provider_calls, COALESCE(SUM(provider_failures), 0) as provider_failures, COALESCE(SUM(tokens), 0) as tokens')
            ->first();

        return Inertia::render('rootadmin/dashboard', [
            'totals' => [
                'tenants' => (int) $byStatus->sum(),
                'active' => (int) ($byStatus['active'] ?? 0),
                'suspended' => (int) ($byStatus['suspended'] ?? 0),
                'users' => User::query()->count(),
            ],
            'sites' => [
                'published' => TenantWebsite::query()
                    ->where('status', WebsiteStatus::Published)
                    ->whereNotNull('published_version_id')
                    ->count(),
            ],
            'appointments' => [
                'upcoming' => Appointment::query()
                    ->where('status', AppointmentStatus::Scheduled)
                    ->where('starts_at', '>=', now())
                    ->count(),
            ],
            'ai' => [
                'period' => now()->format('m/Y'),
                'messages' => (int) $aiUsage->messages,
                'provider_calls' => (int) $aiUsage->provider_calls,
                'provider_failures' => (int) $aiUsage->provider_failures,
                'tokens' => (int) $aiUsage->tokens,
            ],
            'pending' => [
                ['label' => 'Receita mensal recorrente', 'reason' => 'Assinaturas e cobrança ainda não estão implementadas.'],
                ['label' => 'Pagamentos pendentes', 'reason' => 'Integração financeira ainda não está implementada.'],
                ['label' => 'Empresas em teste', 'reason' => 'O cadastro ainda não possui situação de teste.'],
                ['label' => 'Custo estimado de IA', 'reason' => 'Não há tabela de preços do provedor cadastrada.'],
                ['label' => 'Incidentes operacionais', 'reason' => 'Registro de incidentes ainda não está implementado.'],
            ],
            'distribution' => $distribution->map(fn ($row): array => [
                'name' => $row->segment_name,
                'total' => (int) $row->total,
            ])->values(),
            'recent' => $recent,
        ]);
    }
}
