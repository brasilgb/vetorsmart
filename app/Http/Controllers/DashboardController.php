<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\MembershipStatus;
use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
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
            'operations' => $this->operations($tenant),
            'checklist' => $checklist,
            'needsSetup' => collect($checklist)->contains(fn (array $item): bool => ! $item['done']),
            'membershipActive' => $context->membership()?->status === MembershipStatus::Active,
        ]);
    }

    /**
     * Operational indicators computed from the tenant's requests and appointments. A conversion
     * rate is null while there is nothing to measure, so it is never shown as a made-up figure.
     *
     * @return array<string, mixed>
     */
    private function operations(Tenant $tenant): array
    {
        $since = now()->subDays(30);
        $requests = ServiceRequest::query()->forTenant($tenant);
        $appointments = Appointment::query()->forTenant($tenant);

        $recent = (clone $requests)->where('created_at', '>=', $since);
        $recentTotal = (clone $recent)->count();
        $recentScheduled = (clone $recent)->whereIn('status', [
            RequestStatus::Scheduled->value,
            RequestStatus::InProgress->value,
            RequestStatus::Completed->value,
            RequestStatus::NotPerformed->value,
        ])->count();

        return [
            'open_requests' => (clone $requests)->whereIn('status', [RequestStatus::Requested->value, RequestStatus::Triaged->value])->count(),
            'today_appointments' => (clone $appointments)->where('status', AppointmentStatus::Scheduled->value)
                ->whereDate('starts_at', today())->count(),
            'in_progress' => (clone $requests)->where('status', RequestStatus::InProgress->value)->count(),
            'completed_30_days' => (clone $requests)->where('status', RequestStatus::Completed->value)->where('closed_at', '>=', $since)->count(),
            'cancelled_30_days' => (clone $requests)->where('status', RequestStatus::Cancelled->value)->where('closed_at', '>=', $since)->count(),
            'conversion_30_days' => $recentTotal > 0 ? round($recentScheduled / $recentTotal * 100) : null,
            'upcoming' => (clone $appointments)->where('status', AppointmentStatus::Scheduled->value)
                ->where('starts_at', '>=', now())
                ->with(['professional:id,name', 'serviceRequest.customer:id,name', 'serviceRequest.service:id,name'])
                ->orderBy('starts_at')
                ->limit(5)
                ->get()
                ->map(fn (Appointment $appointment): array => [
                    'id' => $appointment->id,
                    'starts_at' => $appointment->starts_at->format('d/m/Y H:i'),
                    'customer' => $appointment->serviceRequest?->customer?->name,
                    'service' => $appointment->serviceRequest?->service?->name,
                    'professional' => $appointment->professional?->name,
                ])
                ->values()
                ->all(),
        ];
    }
}
