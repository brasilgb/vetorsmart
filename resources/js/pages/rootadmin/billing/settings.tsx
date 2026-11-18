import { Head, Link, router, useForm } from '@inertiajs/react';
import { Field, selectClass } from '@/components/vetor-smart/field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { plan as savePlan, settings as saveSettings, test } from '@/routes/rootadmin/billing';
import { index as subscriptionsIndex } from '@/routes/rootadmin/subscriptions';

type Plan = {
    name: string;
    description: string;
    price: string;
    interval_months: number;
    trial_days: number;
    included_users: number | string;
    extra_user_price: string;
    ai_messages_per_month: number | string;
    promotional_price: string;
    promotional_until: string;
};

type Settings = {
    efi_environment: string;
    efi_pix_key: string;
    grace_days: number;
    renewal_days_before: number;
    billet_due_days: number;
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

export default function BillingSettings({ plan, settings }: { plan: Plan; settings: Settings }) {
    const planForm = useForm({ ...plan, included_users: String(plan.included_users), ai_messages_per_month: String(plan.ai_messages_per_month), interval_months: String(plan.interval_months), trial_days: String(plan.trial_days) });
    const settingsForm = useForm<Record<string, string | boolean>>({
        efi_environment: settings.efi_environment,
        efi_pix_key: settings.efi_pix_key,
        grace_days: String(settings.grace_days),
        renewal_days_before: String(settings.renewal_days_before),
        billet_due_days: String(settings.billet_due_days),
        confirm_production: false,
        ...Object.fromEntries(secretFields.map((field) => [field.key, ''])),
    });

    const numberField = (key: keyof typeof planForm.data, label: string, hint?: string) => (
        <Field id={`plan-${key}`} label={label} error={planForm.errors[key]} hint={hint}>
            <Input id={`plan-${key}`} inputMode="decimal" value={String(planForm.data[key] ?? '')} onChange={(event) => planForm.setData(key, event.target.value)} className="h-10" />
        </Field>
    );

    return (
        <>
            <Head title="Plano e Efí · RootAdmin" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Link href={subscriptionsIndex.url()} className="text-sm text-vs-link hover:underline">← Assinaturas</Link>
                    <h1 className="mt-1 text-2xl font-semibold text-vs-text">Plano e cobrança</h1>
                    <p className="text-sm text-vs-muted">Valores comerciais do plano principal, regras de cobrança e integração com a Efí.</p>
                </div>

                <Card className="border-vs-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-vs-text">Plano principal</CardTitle>
                        <CardDescription>Sem valor, o teste funciona e a contratação fica fechada. Valores em reais; contratos existentes mantêm o valor negociado.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={(event) => { event.preventDefault(); planForm.patch(savePlan.url(), { preserveScroll: true }); }} className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <Field id="plan-name" label="Nome" error={planForm.errors.name}>
                                <Input id="plan-name" value={planForm.data.name} onChange={(event) => planForm.setData('name', event.target.value)} className="h-10" />
                            </Field>
                            {numberField('price', 'Mensalidade (R$)', 'Deixe vazio enquanto o valor não for aprovado.')}
                            <Field id="plan-interval_months" label="Período de cobrança" error={planForm.errors.interval_months}>
                                <select id="plan-interval_months" value={planForm.data.interval_months} onChange={(event) => planForm.setData('interval_months', event.target.value)} className={selectClass}>
                                    <option value="1">Mensal</option>
                                    <option value="3">Trimestral</option>
                                    <option value="6">Semestral</option>
                                    <option value="12">Anual</option>
                                </select>
                            </Field>
                            {numberField('trial_days', 'Dias de teste')}
                            {numberField('included_users', 'Usuários incluídos', 'Vazio: sem limite definido.')}
                            {numberField('extra_user_price', 'Usuário adicional (R$)')}
                            {numberField('ai_messages_per_month', 'Mensagens de IA por mês', 'Parâmetro preparado; o limite ainda não é aplicado.')}
                            {numberField('promotional_price', 'Valor promocional (R$)')}
                            <Field id="plan-promotional_until" label="Promoção até" error={planForm.errors.promotional_until}>
                                <Input id="plan-promotional_until" type="date" value={planForm.data.promotional_until} onChange={(event) => planForm.setData('promotional_until', event.target.value)} className="h-10" />
                            </Field>
                            <div className="md:col-span-2 xl:col-span-3">
                                <Field id="plan-description" label="Descrição" error={planForm.errors.description}>
                                    <Input id="plan-description" value={planForm.data.description} onChange={(event) => planForm.setData('description', event.target.value)} className="h-10" />
                                </Field>
                            </div>
                            <div><Button type="submit" disabled={planForm.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Salvar plano</Button></div>
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
                            <Field id="grace_days" label="Dias de carência" error={settingsForm.errors.grace_days as string | undefined}>
                                <Input id="grace_days" type="number" min={0} max={30} value={String(settingsForm.data.grace_days)} onChange={(event) => settingsForm.setData('grace_days', event.target.value)} className="h-10" />
                            </Field>
                            <Field id="renewal_days_before" label="Gerar renovação com antecedência de (dias)" error={settingsForm.errors.renewal_days_before as string | undefined}>
                                <Input id="renewal_days_before" type="number" min={1} max={30} value={String(settingsForm.data.renewal_days_before)} onChange={(event) => settingsForm.setData('renewal_days_before', event.target.value)} className="h-10" />
                            </Field>
                            <Field id="billet_due_days" label="Vencimento do boleto em atraso (dias)" error={settingsForm.errors.billet_due_days as string | undefined}>
                                <Input id="billet_due_days" type="number" min={1} max={30} value={String(settingsForm.data.billet_due_days)} onChange={(event) => settingsForm.setData('billet_due_days', event.target.value)} className="h-10" />
                            </Field>
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
