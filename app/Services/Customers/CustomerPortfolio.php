<?php

namespace App\Services\Customers;

use App\Enums\AppointmentStatus;
use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The customer portfolio of a company, read from its own requests and appointments (nothing is stored apart).
 *
 * Definitions:
 * - completed services: requests of the customer with status "completed" (cancelled or not performed never count);
 * - recorded value: the sum of the amounts the company recorded on those completed services (integer cents). Services
 *   without an amount are counted apart, never as zero. It is a declared value, not a payment received;
 * - recurring: two or more completed services (recurrence comes from services done, not from requests sent);
 * - new in the period: first contact (the customer record) within the last N days;
 * - without recent service: at least one completed service, but none in the last N days. It describes the period
 *   analysed only and does not mean the customer left;
 * - open requests: requested, triaged, scheduled or in progress; scheduled ahead: a scheduled appointment from now on.
 *
 * Every query states the company; the figures of a customer only read requests of the same company.
 */
class CustomerPortfolio
{
    /** Periods offered for the relationship filters, in days. */
    public const array PERIODS = [30, 60, 90];

    /** Minimum completed services for a customer to count as recurring. */
    public const int RECURRING_FROM = 2;

    /**
     * Relationship segments, with their labels.
     *
     * @var array<string, string>
     */
    public const array SEGMENTS = [
        'novos' => 'Novos no período',
        'recorrentes' => 'Recorrentes',
        'sem-atendimento' => 'Sem atendimento recente',
        'em-aberto' => 'Com solicitação em aberto',
        'agendados' => 'Com agendamento futuro',
    ];

    /**
     * Add the figures of each customer to a customer query, as correlated sub-selects (one query for the page).
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function withFigures(Builder $query): Builder
    {
        return $query->select('customers.*')
            ->selectSub($this->completed()->selectRaw('COUNT(*)'), 'completed_count')
            ->selectSub($this->completed()->selectRaw('MAX(closed_at)'), 'last_completed_at')
            ->selectSub($this->completed()->selectRaw('COALESCE(SUM(realized_amount_cents), 0)'), 'revenue_cents')
            ->selectSub($this->completed()->whereNotNull('realized_amount_cents')->selectRaw('COUNT(*)'), 'revenue_known')
            ->selectSub($this->requests()->whereIn('status', $this->openStatuses())->selectRaw('COUNT(*)'), 'open_count')
            ->selectSub($this->upcoming()->selectRaw('MIN(starts_at)'), 'next_appointment_at');
    }

    /**
     * Keep only the customers of a relationship segment, for the last $days days.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function segment(Builder $query, string $segment, int $days): Builder
    {
        $since = now()->subDays($days);

        return match ($segment) {
            'novos' => $query->where('customers.created_at', '>=', $since),
            'recorrentes' => $this->recurring($query),
            'sem-atendimento' => $query->whereExists($this->completed())->whereNotExists($this->completed()->where('closed_at', '>=', $since)),
            'em-aberto' => $query->whereExists($this->requests()->whereIn('status', $this->openStatuses())),
            'agendados' => $query->whereExists($this->upcoming()),
            default => $query,
        };
    }

    /**
     * How many customers of the company are in each segment, for the last $days days.
     *
     * @return array<string, int>
     */
    public function indicators(int $tenantId, int $days): array
    {
        $counts = [];

        foreach (array_keys(self::SEGMENTS) as $segment) {
            $counts[$segment] = $this->segment(Customer::query()->forTenant($tenantId), $segment, $days)->count();
        }

        return $counts;
    }

    /**
     * Relationship figures of the company for a period (Meu Crescimento), with the same definitions as the portfolio:
     * - new: customers whose first contact (record) is in the period;
     * - served: customers with a service completed in the period;
     * - returning: of those, the ones who already had an earlier completed service (they came back to hire);
     * - recurring: customers with RECURRING_FROM or more completed services up to the end of the period;
     * - without recent service: completed before, but nothing in the last $days days (as in the portfolio);
     * - frequency: average interval, in days, between completed services of the recurring customers.
     * Grouped queries only, never one query per customer.
     *
     * @return array{new: int, served: int, returning: int, recurring: int, without_recent: int, frequency_days: int|null}
     */
    public function growth(int $tenantId, CarbonImmutable $from, CarbonImmutable $to, int $days): array
    {
        $completed = ServiceRequest::query()->forTenant($tenantId)
            ->where('status', RequestStatus::Completed->value)
            ->whereNotNull('closed_at')
            ->where('closed_at', '<=', $to)
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) AS total, MIN(closed_at) AS first_at, MAX(closed_at) AS last_at')
            ->selectRaw('SUM(CASE WHEN closed_at >= ? THEN 1 ELSE 0 END) AS in_period', [$from])
            ->toBase()
            ->get();

        $served = $completed->filter(fn (object $row): bool => (int) $row->in_period > 0);
        $recurring = $completed->filter(fn (object $row): bool => (int) $row->total >= self::RECURRING_FROM);
        $intervals = $recurring->map(fn (object $row): float => CarbonImmutable::parse((string) $row->first_at, 'UTC')->diffInDays(CarbonImmutable::parse((string) $row->last_at, 'UTC')) / ((int) $row->total - 1));

        return [
            'new' => Customer::query()->forTenant($tenantId)->whereBetween('created_at', [$from, $to])->count(),
            'served' => $served->count(),
            // Served in the period with two or more completed services up to its end: at least one of them is a return.
            'returning' => $served->filter(fn (object $row): bool => (int) $row->total >= self::RECURRING_FROM)->count(),
            'recurring' => $recurring->count(),
            'without_recent' => $this->segment(Customer::query()->forTenant($tenantId), 'sem-atendimento', $days)->count(),
            'frequency_days' => $intervals->isEmpty() ? null : (int) max(1, round($intervals->avg())),
        ];
    }

    /**
     * Commercial and operational summary of one customer.
     *
     * @return array{requests: int, completed: int, cancelled: int, open: int, revenue: string|null, revenue_known: int, revenue_unknown: int, last_service: array{date: CarbonImmutable, service: string|null}|null, frequency_days: int|null, recurring: bool}
     */
    public function summary(Customer $customer): array
    {
        $requests = fn () => ServiceRequest::query()->forTenant($customer->tenant_id)->where('customer_id', $customer->getKey());

        $row = $requests()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed', [RequestStatus::Completed->value])
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) AS cancelled', [RequestStatus::Cancelled->value, RequestStatus::NotPerformed->value])
            ->selectRaw('SUM(CASE WHEN status IN (?, ?, ?, ?) THEN 1 ELSE 0 END) AS open', $this->openStatuses())
            ->selectRaw('SUM(CASE WHEN status = ? AND realized_amount_cents IS NOT NULL THEN realized_amount_cents ELSE 0 END) AS revenue_cents', [RequestStatus::Completed->value])
            ->selectRaw('SUM(CASE WHEN status = ? AND realized_amount_cents IS NOT NULL THEN 1 ELSE 0 END) AS revenue_known', [RequestStatus::Completed->value])
            ->selectRaw('MIN(CASE WHEN status = ? THEN closed_at END) AS first_completed_at', [RequestStatus::Completed->value])
            ->toBase()
            ->first();

        $completed = (int) ($row->completed ?? 0);
        $known = (int) ($row->revenue_known ?? 0);
        $last = $requests()->where('status', RequestStatus::Completed->value)->whereNotNull('closed_at')->with('service:id,name')->latest('closed_at')->first();

        // Average interval between completed services, only from the second one on (one service has no frequency).
        $frequency = null;

        if ($completed >= 2 && $last?->closed_at !== null && $row->first_completed_at !== null) {
            $span = CarbonImmutable::parse((string) $row->first_completed_at, 'UTC')->diffInDays($last->closed_at);
            $frequency = (int) max(1, round($span / ($completed - 1)));
        }

        return [
            'requests' => (int) ($row->total ?? 0),
            'completed' => $completed,
            'cancelled' => (int) ($row->cancelled ?? 0),
            'open' => (int) ($row->open ?? 0),
            'revenue' => $known > 0 ? Money::format((int) $row->revenue_cents) : null,
            'revenue_known' => $known,
            'revenue_unknown' => $completed - $known,
            'last_service' => $last === null ? null : ['date' => CarbonImmutable::instance($last->closed_at), 'service' => $last->service?->name],
            'frequency_days' => $frequency,
            'recurring' => $completed >= self::RECURRING_FROM,
        ];
    }

    /**
     * Customers with at least RECURRING_FROM completed services.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    private function recurring(Builder $query): Builder
    {
        // The outer query is already limited to the company; a request always belongs to its customer's company.
        return $query->whereIn('customers.id', ServiceRequest::query()->toBase()
            ->select('customer_id')
            ->where('status', RequestStatus::Completed->value)
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) >= ?', [self::RECURRING_FROM]));
    }

    /**
     * Requests of the customer (correlated to the outer customers row, same company).
     */
    private function requests(): QueryBuilder
    {
        return ServiceRequest::query()->toBase()
            ->from('service_requests')
            ->whereColumn('service_requests.customer_id', 'customers.id')
            ->whereColumn('service_requests.tenant_id', 'customers.tenant_id');
    }

    private function completed(): QueryBuilder
    {
        return $this->requests()->where('service_requests.status', RequestStatus::Completed->value);
    }

    private function upcoming(): QueryBuilder
    {
        return Appointment::query()->toBase()
            ->from('appointments')
            ->join('service_requests as upcoming_requests', 'upcoming_requests.id', '=', 'appointments.service_request_id')
            ->whereColumn('upcoming_requests.customer_id', 'customers.id')
            ->whereColumn('appointments.tenant_id', 'customers.tenant_id')
            ->where('appointments.status', AppointmentStatus::Scheduled->value)
            ->where('appointments.starts_at', '>=', now());
    }

    /**
     * @return list<string>
     */
    private function openStatuses(): array
    {
        return [RequestStatus::Requested->value, RequestStatus::Triaged->value, RequestStatus::Scheduled->value, RequestStatus::InProgress->value];
    }
}
