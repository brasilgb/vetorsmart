<?php

namespace App\Http\Controllers\RootAdmin;

use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only view of users linked to companies. Credentials are never selected, and
 * permission changes are not offered here.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = TenantUser::query()
            ->with(['user:id,name,email,status', 'tenant:id,name'])
            ->orderByDesc('id');

        $term = $request->string('q')->trim()->value();

        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->whereHas('user', fn ($inner) => $inner->where('name', 'like', $like)->orWhere('email', 'like', $like));
        }

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->integer('tenant_id'));
        }

        if ($request->filled('role') && in_array($request->string('role')->value(), ['owner', 'admin', 'operator'], true)) {
            $query->where('role', $request->string('role')->value());
        }

        if ($request->filled('status') && in_array($request->string('status')->value(), ['active', 'inactive'], true)) {
            $query->where('status', $request->string('status')->value());
        }

        return Inertia::render('rootadmin/users/index', [
            'filters' => $request->only(['q', 'tenant_id', 'role', 'status']),
            'memberships' => $query->paginate(20)->withQueryString()->through(fn (TenantUser $membership): array => [
                'id' => $membership->id,
                'name' => $membership->user?->name,
                'email' => $membership->user?->email,
                'user_status' => $membership->user?->status->value,
                'tenant' => $membership->tenant?->name,
                'tenant_id' => $membership->tenant_id,
                'role' => $membership->role->value,
                'membership_status' => $membership->status->value,
            ]),
        ]);
    }
}
