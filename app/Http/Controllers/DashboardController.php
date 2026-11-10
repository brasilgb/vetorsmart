<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\TenantSetting;
use App\Services\Tenancy\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Real indicators of the active tenant. Modules without data yet (requests, appointments)
     * are not shown, so no simulated figures are presented.
     */
    public function __invoke(TenantContext $context): Response
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
            'checklist' => $checklist,
            'needsSetup' => collect($checklist)->contains(fn (array $item): bool => ! $item['done']),
            'membershipActive' => $context->membership()?->status === MembershipStatus::Active,
        ]);
    }
}
