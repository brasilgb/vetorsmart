<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\NotificationCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateNotificationPreferencesRequest;
use App\Models\Tenant;
use App\Models\TenantNotification;
use App\Models\TenantSetting;
use App\Services\AuditLogger;
use App\Services\OperationalNotifier;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Notices of the signed-in person in the active company, and the e-mail preferences. A notice of another person or
 * of another company is answered with 404, as if it did not exist.
 */
class NotificationController extends Controller
{
    public function index(Request $request, TenantContext $context): Response
    {
        $tenant = $context->requireTenant();
        $membership = $context->require();
        $user = $this->actor($request);

        $notifications = TenantNotification::query()
            ->ownedBy($tenant, $user)
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $canManageCustomerEmails = Gate::allows('manageSettings', $tenant);

        return Inertia::render('tenant/notifications/index', [
            'notifications' => $notifications->through(fn (TenantNotification $notification): array => [
                'id' => $notification->id,
                'category' => $notification->category->value,
                'category_label' => $notification->category->label(),
                'title' => $notification->title,
                'body' => $notification->body,
                'has_link' => $notification->url !== null,
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at === null ? '' : $tenant->toLocal($notification->created_at)->format('d/m/Y H:i'),
            ]),
            'preferences' => array_map(fn (NotificationCategory $category): array => [
                'key' => $category->value,
                'label' => $category->label(),
                'email' => ($membership->notification_preferences[$category->value] ?? true) !== false,
            ], NotificationCategory::optional()),
            'customerEmails' => $canManageCustomerEmails ? $this->customerEmailsEnabled($tenant) : null,
        ]);
    }

    /**
     * Open the record a notice points to, marking the notice as read.
     */
    public function open(Request $request, TenantContext $context, int $notification): RedirectResponse
    {
        $record = $this->find($request, $context, $notification);

        $record->forceFill(['read_at' => $record->read_at ?? now()])->save();

        $path = $record->url;

        // Only panel paths are stored; anything else falls back to the list instead of leaving the site.
        if ($path === null || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return to_route('tenant.notifications.index');
        }

        return redirect($path);
    }

    public function read(Request $request, TenantContext $context, int $notification): RedirectResponse
    {
        $record = $this->find($request, $context, $notification);

        $record->forceFill(['read_at' => $record->read_at ?? now()])->save();

        return back();
    }

    public function readAll(Request $request, TenantContext $context): RedirectResponse
    {
        TenantNotification::query()
            ->ownedBy($context->requireTenant(), $this->actor($request))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Notificações marcadas como lidas.']);

        return back();
    }

    /**
     * Save the person's e-mail preferences and, for owners and admins, the company switch for customer e-mails.
     */
    public function preferences(UpdateNotificationPreferencesRequest $request, TenantContext $context, AuditLogger $auditLogger): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $membership = $context->require();

        $email = (array) $request->validated('email', []);
        $preferences = $membership->notification_preferences ?? [];

        foreach (NotificationCategory::optional() as $category) {
            if (array_key_exists($category->value, $email)) {
                $preferences[$category->value] = (bool) $email[$category->value];
            }
        }

        $membership->forceFill(['notification_preferences' => $preferences])->save();

        if ($request->has('customer_emails')) {
            Gate::authorize('manageSettings', $tenant);

            $setting = TenantSetting::query()->forTenant($tenant)->firstOrFail();
            $enabled = $request->boolean('customer_emails');

            if ($this->customerEmailsEnabled($tenant) !== $enabled) {
                $setting->forceFill(['settings' => [...($setting->settings ?? []), OperationalNotifier::CUSTOMER_EMAILS_SETTING => $enabled]])->save();
                $auditLogger->record('tenant_settings.customer_emails', $tenant, $setting, ['enabled' => $enabled], $this->actor($request));
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Preferências salvas.']);

        return to_route('tenant.notifications.index');
    }

    private function find(Request $request, TenantContext $context, int $notification): TenantNotification
    {
        return TenantNotification::query()
            ->ownedBy($context->requireTenant(), $this->actor($request))
            ->whereKey($notification)
            ->firstOrFail();
    }

    private function customerEmailsEnabled(Tenant $tenant): bool
    {
        $settings = TenantSetting::query()->forTenant($tenant)->value('settings');
        $settings = is_string($settings) ? json_decode($settings, true) : $settings;

        return (is_array($settings) ? ($settings[OperationalNotifier::CUSTOMER_EMAILS_SETTING] ?? true) : true) !== false;
    }
}
