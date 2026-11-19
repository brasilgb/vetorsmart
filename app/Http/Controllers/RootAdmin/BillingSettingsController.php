<?php

namespace App\Http\Controllers\RootAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RootAdmin\SaveAiCreditPackageRequest;
use App\Http\Requests\RootAdmin\UpdateBillingSettingsRequest;
use App\Http\Requests\RootAdmin\UpdatePlanRequest;
use App\Models\AiCreditPackage;
use App\Models\BillingSetting;
use App\Models\Plan;
use App\Services\AuditLogger;
use App\Services\Billing\BillingEnforcement;
use App\Services\Billing\Efi\EfiChargesClient;
use App\Services\Billing\Efi\EfiException;
use App\Services\Billing\Efi\EfiPixClient;
use App\Services\Billing\PlanCatalog;
use App\Support\PlatformTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The plan (three cycles, users, AI franchise, promotion and approval of the prices), the AI credit packages and the
 * billing settings. Every change is audited with the previous and the new values, except secrets, which are
 * write-only: the screen and the audit show only whether each one is set.
 */
class BillingSettingsController extends Controller
{
    private const array SECRET_FIELDS = ['efi_pix_client_id', 'efi_pix_client_secret', 'efi_pix_certificate', 'efi_charges_client_id', 'efi_charges_client_secret'];

    public function edit(PlanCatalog $catalog, BillingEnforcement $enforcement): Response
    {
        $plan = Plan::main();
        $settings = BillingSetting::current();
        $base = rtrim((string) config('platform.public_url'), '/');

        return Inertia::render('rootadmin/billing/settings', [
            'plan' => [
                'name' => $plan->name,
                'description' => $plan->description ?? '',
                'price' => $this->reais($plan->price_cents),
                'semiannual_price' => $this->reais($plan->semiannual_price_cents),
                'annual_price' => $this->reais($plan->annual_price_cents),
                'trial_days' => $plan->trial_days,
                'included_users' => $plan->included_users ?? '',
                'extra_user_price' => $this->reais($plan->extra_user_price_cents),
                'ai_monthly_credits' => $plan->ai_monthly_credits ?? '',
                'promotional_discount_percent' => $plan->promotional_discount_percent,
                'promotional_until' => $plan->promotional_until?->format('Y-m-d') ?? '',
                'prices_approved' => $plan->pricesApproved(),
                'prices_approved_at' => PlatformTime::dateTime($plan->prices_approved_at),
            ],
            'offer' => array_map(fn (array $cycle): array => [
                'label' => $cycle['label'],
                'total' => $this->brl($cycle['total_cents']),
                'monthly_equivalent' => $this->brl($cycle['monthly_equivalent_cents']),
                'savings_percent' => $cycle['savings_percent'],
            ], $catalog->offer($plan)['cycles']),
            'enforcement' => [
                'active' => $enforcement->active(),
                'enabled_by_environment' => $enforcement->enabledByEnvironment(),
                'starts_at' => PlatformTime::dateTime($settings->enforcement_starts_at),
                'unmet' => $enforcement->unmetPrerequisites(),
            ],
            'packages' => AiCreditPackage::query()->orderBy('credits')->get()->map(fn (AiCreditPackage $package): array => [
                'id' => $package->id,
                'name' => $package->name,
                'credits' => $package->credits,
                'price' => $this->reais($package->price_cents),
                'active' => $package->active,
            ])->all(),
            'settings' => [
                'efi_environment' => $settings->efi_environment,
                'efi_pix_key' => $settings->efi_pix_key ?? '',
                'grace_days' => $settings->grace_days,
                'renewal_days_before' => $settings->renewal_days_before,
                'billet_due_days' => $settings->billet_due_days,
                'ai_tokens_per_credit' => $settings->ai_tokens_per_credit,
                'ai_low_balance_percent' => $settings->ai_low_balance_percent,
                'ai_credit_purchases_enabled' => $settings->ai_credit_purchases_enabled,
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
        $approved = $request->boolean('prices_approved');

        $plan->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price_cents' => $this->cents($data['price'] ?? null),
            'semiannual_price_cents' => $this->cents($data['semiannual_price'] ?? null),
            'annual_price_cents' => $this->cents($data['annual_price'] ?? null),
            'trial_days' => (int) $data['trial_days'],
            'included_users' => $data['included_users'] ?? null,
            'extra_user_price_cents' => $this->cents($data['extra_user_price'] ?? null),
            'ai_monthly_credits' => $data['ai_monthly_credits'] ?? null,
            'promotional_discount_percent' => (int) $data['promotional_discount_percent'],
            'promotional_until' => $data['promotional_until'] ?? null,
        ]);

        // A price change withdraws the approval: the new values need a new decision.
        $pricesChanged = $plan->isDirty(['price_cents', 'semiannual_price_cents', 'annual_price_cents', 'extra_user_price_cents']);
        $plan->forceFill(['prices_approved_at' => $approved && ! ($pricesChanged && $plan->pricesApproved()) ? ($plan->prices_approved_at ?? now()) : null]);

        $this->saveAudited($plan, 'billing.plan_updated', $auditLogger, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => $pricesChanged && $approved && $plan->prices_approved_at === null
            ? 'Plano atualizado. Os valores mudaram: marque a aprovação de novo para liberá-los.'
            : 'Plano atualizado. Contratos existentes mantêm as condições contratadas.']);

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
            'ai_tokens_per_credit' => (int) $data['ai_tokens_per_credit'],
            'ai_low_balance_percent' => (int) $data['ai_low_balance_percent'],
            'ai_credit_purchases_enabled' => $request->boolean('ai_credit_purchases_enabled'),
        ];

        foreach (self::SECRET_FIELDS as $field) {
            if (filled($data[$field] ?? null)) {
                $changes[$field] = trim((string) $data[$field]);
            }
        }

        $settings->forceFill($changes);
        $this->saveAudited($settings, 'billing.settings_updated', $auditLogger, $request, self::SECRET_FIELDS);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Configurações de cobrança salvas.']);

        return to_route('rootadmin.billing.edit');
    }

    public function storePackage(SaveAiCreditPackageRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $package = new AiCreditPackage;
        $this->fillPackage($package, $request);
        $this->saveAudited($package, 'billing.ai_package_created', $auditLogger, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pacote de créditos criado.']);

        return to_route('rootadmin.billing.edit');
    }

    public function updatePackage(SaveAiCreditPackageRequest $request, AiCreditPackage $package, AuditLogger $auditLogger): RedirectResponse
    {
        $this->fillPackage($package, $request);
        $this->saveAudited($package, 'billing.ai_package_updated', $auditLogger, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pacote atualizado. Compras já feitas mantêm os valores.']);

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

    private function fillPackage(AiCreditPackage $package, SaveAiCreditPackageRequest $request): void
    {
        $data = $request->validated();

        $package->fill([
            'name' => $data['name'],
            'credits' => (int) $data['credits'],
            'price_cents' => (int) $this->cents($data['price']),
            'active' => $request->boolean('active', true),
        ]);
    }

    /**
     * Save the model and audit the changed fields with the previous and the new values. Secret fields are listed by
     * name only.
     *
     * @param  list<string>  $secretFields
     */
    private function saveAudited(Model $model, string $action, AuditLogger $auditLogger, Request $request, array $secretFields = []): void
    {
        $changes = [];

        foreach ($model->getDirty() as $field => $value) {
            $changes[$field] = in_array($field, $secretFields, true)
                ? 'alterado'
                : ['de' => $model->getOriginal($field), 'para' => $model->getAttribute($field)];
        }

        $model->save();

        if ($changes !== []) {
            $auditLogger->record($action, null, $model, ['changes' => $changes], $this->actor($request));
        }
    }

    private function cents(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) round(((float) $value) * 100);
    }

    private function reais(?int $cents): string
    {
        return $cents === null ? '' : number_format($cents / 100, 2, '.', '');
    }

    private function brl(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }
}
