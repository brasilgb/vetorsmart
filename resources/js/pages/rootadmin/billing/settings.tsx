import { Head, Link, router, useForm } from '@inertiajs/react';
import { Field, selectClass } from '@/components/vetor-smart/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { plan as savePlan, settings as saveSettings, test } from '@/routes/rootadmin/billing';
import { store as storePackage, update as updatePackage } from '@/routes/rootadmin/billing/packages';
import { index as subscriptionsIndex } from '@/routes/rootadmin/subscriptions';

type Plan = {
    name: string;
    description: string;
    price: string;
    semiannual_price: string;
    annual_price: string;
    trial_days: number;
    included_users: number | string;
    extra_user_price: string;
    ai_monthly_credits: number | string;
    promotional_discount_percent: number;
    promotional_until: string;
    prices_approved: boolean;
    prices_approved_at: string | null;
};

type OfferCycle = { label: string; total: string; monthly_equivalent: string; savings_percent: number };

type Package = { id: number; name: string; credits: number; price: string; active: boolean };

type Enforcement = { active: boolean; enabled_by_environment: boolean; starts_at: string | null; unmet: string[] };

type Settings = {
    efi_environment: string;
    efi_pix_key: string;
    grace_days: number;
    renewal_days_before: number;
    billet_due_days: number;
    ai_tokens_per_credit: number;
    ai_low_balance_percent: number;
    ai_credit_purchases_enabled: boolean;
    configured: Record<string, boolean>;
    pix_ready: boolean;
    charges_ready: boolean;
    connection_tested_at: string | null;
    webhook_pix_url: string;
};

const secretFields: { key: string; label: string; textarea?: boolean }[] = [
    { key: 'efi_pix_client_id', label: 'Client ID (API Pix)' },
    { key: 'efi_pix_client_secret', label: 'Client Secret (API Pix)' },
    { key: 'efi_pix_certificate', label: 'Certificado PEM (API Pix)', textarea: true },
    { key: 'efi_charges_client_id', label: 'Client ID (API Cobranças)' },
    { key: 'efi_charges_client_secret', label: 'Client Secret (API Cobranças)' },
];

type PackageForm = { name: string; credits: string; price: string; active: boolean };

function PackageFields({ prefix, form }: { prefix: string; form: ReturnType<typeof useForm<PackageForm>> }) {
    return (
        <>
            <Field id={`${prefix}-name`} label="Nome" error={form.errors.name}>
                <Input id={`${prefix}-name`} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className="h-10" />
            </Field>
            <Field id={`${prefix}-credits`} label="Créditos" error={form.errors.credits}>
                <Input id={`${prefix}-credits`} inputMode="numeric" value={form.data.credits} onChange={(event) => form.setData('credits', event.target.value)} className="h-10" />
            </Field>
            <Field id={`${prefix}-price`} label="Valor (R$)" error={form.errors.price}>
                <Input id={`${prefix}-price`} inputMode="decimal" value={form.data.price} onChange={(event) => form.setData('price', event.target.value)} className="h-10" />
            </Field>
            <div className="flex h-10 items-center gap-2 self-end">
                <Checkbox id={`${prefix}-active`} checked={form.data.active} onCheckedChange={(checked) => form.setData('active', checked === true)} />
                <Label htmlFor={`${prefix}-active`} className="text-vs-text">Disponível para compra</Label>
            </div>
        </>
    );
}

function PackageRow({ pack }: { pack: Package }) {
    const form = useForm<PackageForm>({ name: pack.name, credits: String(pack.credits), price: pack.price, active: pack.active });

    return (
        <form onSubmit={(event) => { event.preventDefault(); form.patch(updatePackage.url(pack.id), { preserveScroll: true }); }} className="grid gap-3 rounded-lg border border-vs-border/70 p-3 sm:grid-cols-2 lg:grid-cols-5">
            <PackageFields prefix={`package-${pack.id}`} form={form} />
            <div className="self-end"><Button type="submit" variant="outline" disabled={form.processing} className="h-10">Salvar pacote</Button></div>
        </form>
    );
}

export default function BillingSettings({ plan, offer, packages, settings, enforcement }: { plan: Plan; offer: OfferCycle[]; packages: Package[]; settings: Settings; enforcement: Enforcement }) {
    const planForm = useForm({
        ...plan,
        included_users: String(plan.included_users),
        ai_monthly_credits: String(plan.ai_monthly_credits),
        trial_days: String(plan.trial_days),
        promotional_discount_percent: String(plan.promotional_discount_percent),
    });
    const packageForm = useForm<PackageForm>({ name: '', credits: '', price: '', active: true });
    const settingsForm = useForm<Record<string, string | boolean>>({
        efi_environment: settings.efi_environment,
        efi_pix_key: settings.efi_pix_key,
        grace_days: String(settings.grace_days),
        renewal_days_before: String(settings.renewal_days_before),
        billet_due_days: String(settings.billet_due_days),
        ai_tokens_per_credit: String(settings.ai_tokens_per_credit),
        ai_low_balance_percent: String(settings.ai_low_balance_percent),
        ai_credit_purchases_enabled: settings.ai_credit_purchases_enabled,
        confirm_production: false,
        ...Object.fromEntries(secretFields.map((field) => [field.key, ''])),
    });

    const numberField = (key: 'price' | 'semiannual_price' | 'annual_price' | 'trial_days' | 'included_users' | 'extra_user_price' | 'ai_monthly_credits' | 'promotional_discount_percent', label: string, hint?: string) => (
        <Field id={`plan-${key}`} label={label} error={planForm.errors[key]} hint={hint}>
            <Input id={`plan-${key}`} inputMode="decimal" value={String(planForm.data[key] ?? '')} onChange={(event) => planForm.setData(key, event.target.value)} className="h-10" />
        </Field>
    );

    const settingsNumber = (key: string, label: string, min: number, max: number, hint?: string) => (
        <Field id={key} label={label} error={settingsForm.errors[key] as string | undefined} hint={hint}>
            <Input id={key} type="number" min={min} max={max} value={String(settingsForm.data[key])} onChange={(event) => settingsForm.setData(key, event.target.value)} className="h-10" />
        </Field>
    );

    return (
        <>
            <Head title="Plano e Efí · RootAdmin" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Link href={subscriptionsIndex.url()} className="text-sm text-vs-link hover:underline">← Assinaturas</Link>
                    <h1 className="mt-1 text-2xl font-semibold text-vs-text">Plano e cobrança</h1>
                    <p className="text-sm text-vs-muted">Valores do plano, créditos de IA, regras de cobrança e integração com a Efí. Toda alteração fica na auditoria.</p>
                </div>

                <Card className="border-vs-border/70 shadow-sm">
                    <CardHeader>
                        <div className="flex flex-wrap items-center gap-3">
                            <CardTitle className="text-vs-text">Cobrança em vigor</CardTitle>
                            <Badge variant="outline" className={enforcement.active ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400' : 'border-amber-500 text-amber-700 dark:text-amber-400'}>
                                {enforcement.active ? `Em vigor desde ${enforcement.starts_at}` : 'Desligada: uso liberado, sem cobrança'}
                            </Badge>
                        </div>
                        <CardDescription>
                            Enquanto desligada, testes não terminam em suspensão, nenhuma cobrança é aberta e vagas pagas e créditos não são concedidos.
                            Só é ligada pelo comando auditado <code>billing:start-enforcement</code>, com <code>PLATFORM_BILLING_ENFORCEMENT_ENABLED=true</code> e
                            os pré-requisitos abaixo. Esta tela não altera essa situação.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="text-sm">
                        {enforcement.unmet.length === 0 ? (
                            <p className="text-vs-text">Todos os pré-requisitos verificáveis estão atendidos.</p>
                        ) : (
                            <ul className="grid list-disc gap-1 pl-5 text-vs-text">
                                {enforcement.unmet.map((item) => (
                                    <li key={item}>{item}</li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card className="border-vs-border/70 shadow-sm">
                    <CardHeader>
                        <div className="flex flex-wrap items-center gap-3">
                            <CardTitle className="text-vs-text">Plano principal</CardTitle>
                            {plan.prices_approved ? (
                                <Badge variant="outline" className="border-emerald-600 text-emerald-700 dark:text-emerald-400">Valores aprovados{plan.prices_approved_at && ` em ${plan.prices_approved_at}`}</Badge>
                            ) : (
                                <Badge variant="outline" className="border-amber-500 text-amber-700 dark:text-amber-400">Valores provisórios</Badge>
                            )}
                        </div>
                        <CardDescription>
                            Valor total de cada ciclo, em reais. Ciclo sem valor não pode ser contratado. Contratos existentes mantêm as condições contratadas.
                            Enquanto os valores não forem aprovados, nenhuma cobrança é emitida no ambiente de produção.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-6">
                        {offer.length > 0 && (
                            <ul className="grid gap-3 sm:grid-cols-3">
                                {offer.map((cycle) => (
                                    <li key={cycle.label} className="rounded-lg border border-vs-border/70 p-3 text-sm">
                                        <p className="font-medium text-vs-text">{cycle.label}{cycle.savings_percent > 0 && ` · −${cycle.savings_percent}%`}</p>
                                        <p className="text-vs-text">{cycle.total}</p>
                                        <p className="text-vs-muted">equivale a {cycle.monthly_equivalent}/mês</p>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                // The server may withdraw the approval (a price changed): show what it saved, not what was sent.
                                planForm.patch(savePlan.url(), { preserveScroll: true, onSuccess: (page) => planForm.setData('prices_approved', (page.props as unknown as { plan: Plan }).plan.prices_approved) });
                            }}
                            className="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                        >
                            <Field id="plan-name" label="Nome" error={planForm.errors.name}>
                                <Input id="plan-name" value={planForm.data.name} onChange={(event) => planForm.setData('name', event.target.value)} className="h-10" />
                            </Field>
                            {numberField('price', 'Mensal (R$)')}
                            {numberField('semiannual_price', 'Semestral, total (R$)')}
                            {numberField('annual_price', 'Anual, total (R$)')}
                            {numberField('trial_days', 'Dias de teste')}
                            {numberField('included_users', 'Usuários incluídos', 'Vazio: sem limite definido.')}
                            {numberField('extra_user_price', 'Usuário adicional por mês (R$)')}
                            {numberField('ai_monthly_credits', 'Créditos de IA por mês', 'Vazio: franquia não definida e créditos não aplicados.')}
                            {numberField('promotional_discount_percent', 'Desconto promocional (%)', 'Aplicado a novos contratos; 0 desliga.')}
                            <Field id="plan-promotional_until" label="Promoção até" error={planForm.errors.promotional_until} hint="Vazio: sem data final.">
                                <Input id="plan-promotional_until" type="date" value={planForm.data.promotional_until} onChange={(event) => planForm.setData('promotional_until', event.target.value)} className="h-10" />
                            </Field>
                            <div className="md:col-span-2 xl:col-span-3">
                                <Field id="plan-description" label="Descrição" error={planForm.errors.description}>
                                    <Input id="plan-description" value={planForm.data.description} onChange={(event) => planForm.setData('description', event.target.value)} className="h-10" />
                                </Field>
                            </div>
                            <div className="flex items-center gap-3 md:col-span-2 xl:col-span-3">
                                <Checkbox id="plan-prices_approved" checked={planForm.data.prices_approved} onCheckedChange={(checked) => planForm.setData('prices_approved', checked === true)} />
                                <Label htmlFor="plan-prices_approved" className="text-vs-text">Valores aprovados pelos sócios (libera cobranças em produção)</Label>
                            </div>
                            <div><Button type="submit" disabled={planForm.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Salvar plano</Button></div>
                        </form>
                    </CardContent>
                </Card>

                <Card className="border-vs-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-vs-text">Pacotes de créditos de IA</CardTitle>
                        <CardDescription>Créditos comprados não expiram e são cobrados à parte da assinatura. Compras já feitas mantêm o valor do momento.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        {packages.length === 0 ? (
                            <p className="text-sm text-vs-muted">Nenhum pacote cadastrado.</p>
                        ) : (
                            packages.map((pack) => <PackageRow key={pack.id} pack={pack} />)
                        )}
                        <form onSubmit={(event) => { event.preventDefault(); packageForm.post(storePackage.url(), { preserveScroll: true, onSuccess: () => packageForm.reset() }); }} className="grid gap-3 rounded-lg border border-dashed border-vs-border p-3 sm:grid-cols-2 lg:grid-cols-5">
                            <PackageFields prefix="package-new" form={packageForm} />
                            <div className="self-end"><Button type="submit" disabled={packageForm.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Criar pacote</Button></div>
                        </form>
                    </CardContent>
                </Card>

                <Card className="border-vs-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-vs-text">Efí e regras de cobrança</CardTitle>
                        <CardDescription>
                            As credenciais ficam criptografadas e nunca voltam para a tela; deixe o campo vazio para manter o valor atual.
                            {' '}API Pix: {settings.pix_ready ? 'configurada' : 'incompleta'} · API Cobranças: {settings.charges_ready ? 'configurada' : 'incompleta'}
                            {settings.connection_tested_at && ` · conexão testada em ${settings.connection_tested_at}`}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-6">
                        <form onSubmit={(event) => { event.preventDefault(); settingsForm.patch(saveSettings.url(), { preserveScroll: true, onSuccess: () => settingsForm.reset(...secretFields.map((field) => field.key)) }); }} className="grid gap-4 md:grid-cols-2">
                            <Field id="efi_environment" label="Ambiente" error={settingsForm.errors.efi_environment as string | undefined}>
                                <select id="efi_environment" value={String(settingsForm.data.efi_environment)} onChange={(event) => settingsForm.setData('efi_environment', event.target.value)} className={selectClass}>
                                    <option value="homologation">Homologação (sem cobranças reais)</option>
                                    <option value="production">Produção (cobranças reais)</option>
                                </select>
                            </Field>
                            <Field id="efi_pix_key" label="Chave Pix da conta" error={settingsForm.errors.efi_pix_key as string | undefined}>
                                <Input id="efi_pix_key" value={String(settingsForm.data.efi_pix_key)} onChange={(event) => settingsForm.setData('efi_pix_key', event.target.value)} className="h-10" />
                            </Field>
                            {secretFields.map((field) => (
                                <Field key={field.key} id={field.key} label={`${field.label}${settings.configured[field.key] ? ' · configurado' : ''}`} error={settingsForm.errors[field.key] as string | undefined}>
                                    {field.textarea ? (
                                        <textarea id={field.key} rows={3} value={String(settingsForm.data[field.key])} onChange={(event) => settingsForm.setData(field.key, event.target.value)} autoComplete="off" spellCheck={false} className="w-full rounded-md border border-input bg-card px-3 py-2 font-mono text-xs text-vs-text" />
                                    ) : (
                                        <Input id={field.key} type="password" value={String(settingsForm.data[field.key])} onChange={(event) => settingsForm.setData(field.key, event.target.value)} autoComplete="off" className="h-10" />
                                    )}
                                </Field>
                            ))}
                            {settingsNumber('grace_days', 'Dias de carência', 0, 30)}
                            {settingsNumber('renewal_days_before', 'Gerar renovação com antecedência de (dias)', 1, 30)}
                            {settingsNumber('billet_due_days', 'Vencimento do boleto em atraso (dias)', 1, 30)}
                            {settingsNumber('ai_tokens_per_credit', 'Tokens por crédito de IA', 1, 10000000, 'Conversão interna entre consumo e créditos.')}
                            {settingsNumber('ai_low_balance_percent', 'Alerta de saldo baixo (% da franquia)', 0, 100)}
                            <div className="flex items-center gap-3 self-end">
                                <Checkbox id="ai_credit_purchases_enabled" checked={settingsForm.data.ai_credit_purchases_enabled === true} onCheckedChange={(checked) => settingsForm.setData('ai_credit_purchases_enabled', checked === true)} />
                                <Label htmlFor="ai_credit_purchases_enabled" className="text-vs-text">Liberar compra de créditos de IA</Label>
                            </div>
                            {settingsForm.data.efi_environment === 'production' && (
                                <div className="flex items-center gap-3 md:col-span-2">
                                    <Checkbox id="confirm_production" checked={settingsForm.data.confirm_production === true} onCheckedChange={(checked) => settingsForm.setData('confirm_production', checked === true)} />
                                    <Label htmlFor="confirm_production" className="text-vs-text">Entendo que o ambiente de produção gera cobranças reais.</Label>
                                </div>
                            )}
                            {settingsForm.errors.confirm_production && <p className="text-sm text-vs-danger md:col-span-2">{String(settingsForm.errors.confirm_production)}</p>}
                            <div className="flex flex-wrap gap-3 md:col-span-2">
                                <Button type="submit" disabled={settingsForm.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Salvar configurações</Button>
                                <Button type="button" variant="outline" className="h-10" onClick={() => router.post(test.url(), {}, { preserveScroll: true })}>Testar conexão</Button>
                            </div>
                        </form>

                        <div className="grid gap-1 text-sm">
                            <p className="font-medium text-vs-text">Webhook Pix para cadastrar na Efí</p>
                            <p className="text-vs-muted">A Efí acrescenta /pix ao endereço. O trecho final é um segredo: não o compartilhe.</p>
                            <code className="rounded-md bg-vs-surface px-3 py-2 font-mono text-xs break-all text-vs-text">{settings.webhook_pix_url}</code>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
