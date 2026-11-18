<?php

namespace App\Http\Controllers\RootAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RootAdmin\UpdateBillingSettingsRequest;
use App\Http\Requests\RootAdmin\UpdatePlanRequest;
use App\Models\BillingSetting;
use App\Models\Plan;
use App\Services\AuditLogger;
use App\Services\Billing\Efi\EfiChargesClient;
use App\Services\Billing\Efi\EfiException;
use App\Services\Billing\Efi\EfiPixClient;
use App\Support\PlatformTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The main plan and the billing settings. Secrets are write-only: the screen shows only whether each one is set.
 */
class BillingSettingsController extends Controller
{
    private const array SECRET_FIELDS = ['efi_pix_client_id', 'efi_pix_client_secret', 'efi_pix_certificate', 'efi_charges_client_id', 'efi_charges_client_secret'];

    public function edit(): Response
    {
        $plan = Plan::main();
        $settings = BillingSetting::current();
        $base = rtrim((string) config('platform.public_url'), '/');

        return Inertia::render('rootadmin/billing/settings', [
            'plan' => [
                'name' => $plan->name,
                'description' => $plan->description ?? '',
                'price' => $this->reais($plan->price_cents),
                'interval_months' => $plan->interval_months,
                'trial_days' => $plan->trial_days,
                'included_users' => $plan->included_users ?? '',
                'extra_user_price' => $this->reais($plan->extra_user_price_cents),
                'ai_messages_per_month' => $plan->ai_messages_per_month ?? '',
                'promotional_price' => $this->reais($plan->promotional_price_cents),
                'promotional_until' => $plan->promotional_until?->format('Y-m-d') ?? '',
            ],
            'settings' => [
                'efi_environment' => $settings->efi_environment,
                'efi_pix_key' => $settings->efi_pix_key ?? '',
                'grace_days' => $settings->grace_days,
                'renewal_days_before' => $settings->renewal_days_before,
                'billet_due_days' => $settings->billet_due_days,
                'configured' => array_combine(self::SECRET_FIELDS, array_map(fn (string $field): bool => filled($settings->{$field}), self::SECRET_FIELDS)),
                'pix_ready' => $settings->pixConfigured(),
                'charges_ready' => $settings->chargesConfigured(),
                'connection_tested_at' => PlatformTime::dateTime($settings->connection_tested_at),
                'webhook_pix_url' => $base.'/api/webhooks/efi/'.$settings->webhookSecret(),
            ],
        ]);
    }

    public function updatePlan(UpdatePlanRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $plan = Plan::main();
        $data = $request->validated();

        $plan->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price_cents' => $this->cents($data['price'] ?? null),
            'interval_months' => (int) $data['interval_months'],
            'trial_days' => (int) $data['trial_days'],
            'included_users' => $data['included_users'] ?? null,
            'extra_user_price_cents' => $this->cents($data['extra_user_price'] ?? null),
            'ai_messages_per_month' => $data['ai_messages_per_month'] ?? null,
            'promotional_price_cents' => $this->cents($data['promotional_price'] ?? null),
            'promotional_until' => $data['promotional_until'] ?? null,
        ]);
        $changed = array_keys($plan->getDirty());
        $plan->save();

        if ($changed !== []) {
            $auditLogger->record('billing.plan_updated', null, $plan, ['fields' => $changed], $this->actor($request));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plano atualizado. Contratos existentes mantêm o valor negociado.']);

        return to_route('rootadmin.billing.edit');
    }

    public function updateSettings(UpdateBillingSettingsRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $settings = BillingSetting::current();
        $data = $request->validated();

        $changes = [
            'efi_environment' => $data['efi_environment'],
            'efi_pix_key' => $data['efi_pix_key'] ?? $settings->efi_pix_key,
            'grace_days' => (int) $data['grace_days'],
            'renewal_days_before' => (int) $data['renewal_days_before'],
            'billet_due_days' => (int) $data['billet_due_days'],
        ];

        foreach (self::SECRET_FIELDS as $field) {
            if (filled($data[$field] ?? null)) {
                $changes[$field] = trim((string) $data[$field]);
            }
        }

        $settings->forceFill($changes);
        $changed = array_keys($settings->getDirty());
        $settings->save();

        // Field names only: the audit never receives a secret.
        $auditLogger->record('billing.settings_updated', null, $settings, ['fields' => $changed], $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Configurações de cobrança salvas.']);

        return to_route('rootadmin.billing.edit');
    }

    /**
     * Authenticate at Efí without creating any charge.
     */
    public function test(Request $request, EfiPixClient $pix, EfiChargesClient $charges): RedirectResponse
    {
        $results = [];

        foreach (['Pix' => $pix, 'Cobranças' => $charges] as $name => $client) {
            try {
                $client->testConnection();
                $results[] = "{$name}: ok";
            } catch (EfiException $exception) {
                $results[] = $exception->getMessage();
            }
        }

        if (! collect($results)->contains(fn (string $line): bool => ! str_ends_with($line, ': ok'))) {
            BillingSetting::current()->forceFill(['connection_tested_at' => now()])->save();
        }

        Inertia::flash('toast', ['type' => collect($results)->every(fn (string $line): bool => str_ends_with($line, ': ok')) ? 'success' : 'error', 'message' => implode(' · ', $results)]);

        return to_route('rootadmin.billing.edit');
    }

    private function cents(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) round(((float) $value) * 100);
    }

    private function reais(?int $cents): string
    {
        return $cents === null ? '' : number_format($cents / 100, 2, '.', '');
    }
}
