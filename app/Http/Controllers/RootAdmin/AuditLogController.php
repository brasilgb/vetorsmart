<?php

namespace App\Http\Controllers\RootAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
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

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from')?->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to')?->endOfDay());
        }

        return Inertia::render('rootadmin/audit/index', [
            'filters' => $request->only(['action', 'tenant_id', 'from', 'to']),
            'entries' => $query->paginate(30)->withQueryString()->through(fn (AuditLog $entry): array => [
                'id' => $entry->id,
                'created_at' => $entry->created_at->format('d/m/Y H:i'),
                'action' => $entry->action,
                'tenant' => $entry->tenant?->name,
                'user' => $entry->user?->name,
                'subject' => $entry->auditable_type === null ? null : class_basename($entry->auditable_type).' #'.$entry->auditable_id,
                'metadata' => $entry->metadata,
            ]),
        ]);
    }
}
