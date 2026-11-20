<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\TenantSetting;
use App\Services\Operations\WorkCenter;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard of the company: its work center. The queues that need action come first (WorkCenter), then today's
 * agenda, the next visits and a short summary; the setup checklist shows only while something is missing. Every figure
 * comes from the company's own records, and every action goes through the existing routes and policies.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, TenantContext $context, WorkCenter $workCenter): Response
    {
        $tenant = $context->requireTenant();

        $settings = TenantSetting::query()->forTenant($tenant)->first();

        $metrics = [
            'customers' => Customer::query()->forTenant($tenant)->where('status', 'active')->count(),
            'services' => Service::query()->forTenant($tenant)->where('active', true)->count(),
            'professionals' => Professional::query()->forTenant($tenant)->where('active', true)->count(),
        ];

        $checklist = [
            ['key' => 'company', 'label' => 'Completar dados da empresa', 'done' => filled($settings?->description) && filled($tenant->phone)],
            ['key' => 'service', 'label' => 'Cadastrar o primeiro serviço', 'done' => $metrics['services'] > 0],
            ['key' => 'professional', 'label' => 'Cadastrar um profissional', 'done' => $metrics['professionals'] > 0],
        ];

        return Inertia::render('dashboard', [
            'metrics' => $metrics,
            'work' => $workCenter->for($tenant, $request->user()),
            'checklist' => $checklist,
            'needsSetup' => collect($checklist)->contains(fn (array $item): bool => ! $item['done']),
            'membershipActive' => $context->membership()?->status === MembershipStatus::Active,
        ]);
    }
}
