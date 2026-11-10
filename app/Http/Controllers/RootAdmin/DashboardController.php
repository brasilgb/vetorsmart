<?php

namespace App\Http\Controllers\RootAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Platform indicators computed with grouped queries. Lists are never loaded to count them.
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

        return Inertia::render('rootadmin/dashboard', [
            'totals' => [
                'tenants' => (int) $byStatus->sum(),
                'active' => (int) ($byStatus['active'] ?? 0),
                'suspended' => (int) ($byStatus['suspended'] ?? 0),
                'users' => User::query()->count(),
            ],
            'distribution' => $distribution->map(fn ($row): array => [
                'name' => $row->segment_name,
                'total' => (int) $row->total,
            ])->values(),
            'recent' => $recent,
        ]);
    }
}
