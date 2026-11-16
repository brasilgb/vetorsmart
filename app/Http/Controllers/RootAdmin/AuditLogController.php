<?php

namespace App\Http\Controllers\RootAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\PlatformTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Audit trail with basic filters. Metadata was already sanitized when it was written.
     */
    public function index(Request $request): Response
    {
        $query = AuditLog::query()
            ->with(['tenant:id,name', 'user:id,name'])
            ->orderByDesc('id');

        $action = $request->string('action')->trim()->value();

        if ($action !== '') {
            $query->where('action', 'like', addcslashes($action, '%_\\').'%');
        }

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->integer('tenant_id'));
        }

        $from = $this->dayFilter($request, 'from');

        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        }

        $to = $this->dayFilter($request, 'to');

        if ($to !== null) {
            $query->where('created_at', '<', $to->addDay());
        }

        return Inertia::render('rootadmin/audit/index', [
            'filters' => $request->only(['action', 'tenant_id', 'from', 'to']),
            'entries' => $query->paginate(30)->withQueryString()->through(fn (AuditLog $entry): array => [
                'id' => $entry->id,
                'created_at' => PlatformTime::dateTime($entry->created_at),
                'action' => $entry->action,
                'tenant' => $entry->tenant?->name,
                'user' => $entry->user?->name,
                'subject' => $entry->auditable_type === null ? null : class_basename($entry->auditable_type).' #'.$entry->auditable_id,
                'metadata' => $entry->metadata,
            ]),
        ]);
    }

    /**
     * Start of the filtered day, typed in the platform time zone, as a UTC instant. Malformed dates are ignored.
     */
    private function dayFilter(Request $request, string $key): ?CarbonImmutable
    {
        $value = $request->string($key)->trim()->value();

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) !== 1 || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return null;
        }

        return PlatformTime::startOfDay($value);
    }
}
