import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Field } from '@/components/vetor-smart/field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { settle, sync } from '@/routes/rootadmin/charges';
import { cancel, extend, index as subscriptionsIndex, terms } from '@/routes/rootadmin/subscriptions';
import { show as tenantShow } from '@/routes/rootadmin/tenants';

type Subscription = {
    id: number;
    tenant: string;
    tenant_id: number;
    tenant_status: string;
    plan: string;
    status: string;
    status_label: string;
    payment_method: string | null;
    amount: string | null;
    price: string;
    discount_percent: number;
    discount_until: string;
    trial_ends_at: string | null;
    current_period: string | null;
    grace_ends_at: string | null;
    cancel_at_period_end: boolean;
};

type Charge = {
    id: number;
    period: string;
    amount: string;
    due_date: string;
    method: string;
    status: string;
    status_label: string;
    gateway_id: string | null;
    paid_at: string | null;
    paid_via: string | null;
    attempts: number;
    error: string | null;
};

type Event = { id: number; type: string; from: string | null; to: string | null; source: string; note: string | null; user: string | null; at: string | null };

function SettleCharge({ charge }: { charge: Charge }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });

    if (!open) {
        return <Button type="button" size="sm" variant="outline" onClick={() => setOpen(true)}>Baixa manual</Button>;
    }

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.post(settle.url({ charge: charge.id }), { preserveScroll: true });
            }}
            className="grid w-64 gap-2"
        >
            <label htmlFor={`reason-${charge.id}`} className="text-xs font-medium text-vs-text">Motivo da baixa manual (registrado na auditoria)</label>
            <Input id={`reason-${charge.id}`} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} className="h-9" />
            <InputError message={form.errors.reason} />
            <div className="flex gap-2">
                <Button type="submit" size="sm" disabled={form.processing} className="bg-vs-blue text-white hover:bg-vs-blue-hover">Registrar pagamento</Button>
                <Button type="button" size="sm" variant="ghost" onClick={() => setOpen(false)}>Voltar</Button>
            </div>
        </form>
    );
}

export default function SubscriptionShow({ subscription, charges, events }: { subscription: Subscription; charges: Charge[]; events: Event[] }) {
    const termsForm = useForm({ price: subscription.price, discount_percent: String(subscription.discount_percent), discount_until: subscription.discount_until });
    const extendForm = useForm({ days: '7' });
    const cancelForm = useForm({ reason: '' });

    return (
        <>
            <Head title={`Assinatura · ${subscription.tenant}`} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Link href={subscriptionsIndex.url()} className="text-sm text-vs-link hover:underline">← Assinaturas</Link>
                    <h1 className="mt-1 text-2xl font-semibold text-vs-text">{subscription.tenant}</h1>
                    <p className="text-sm text-vs-muted">
                        {subscription.plan} · {subscription.status_label}
                        {subscription.cancel_at_period_end && ' · cancelamento agendado'}
                        {subscription.tenant_status !== 'active' && ' · empresa suspensa pela administração'}
                        {' · '}
                        <Link href={tenantShow.url({ tenant: subscription.tenant_id })} className="text-vs-link hover:underline">dados da empresa</Link>
                    </p>
                </div>

                <Card className="border-vs-border/70 shadow-sm">
                    <CardContent className="pt-6">
                        <dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                            <div><dt className="text-vs-muted">Valor do período</dt><dd className="font-medium text-vs-text">{subscription.amount ? `R$ ${subscription.amount}` : 'Plano sem valor definido'}</dd></div>
                            <div><dt className="text-vs-muted">Forma de pagamento</dt><dd className="font-medium text-vs-text">{subscription.payment_method ?? 'Não escolhida'}</dd></div>
                            <div><dt className="text-vs-muted">Fim do teste</dt><dd className="font-medium text-vs-text">{subscription.trial_ends_at ?? '—'}</dd></div>
                            <div><dt className="text-vs-muted">Período pago</dt><dd className="font-medium text-vs-text">{subscription.current_period ?? '—'}</dd></div>
                            <div><dt className="text-vs-muted">Fim da carência</dt><dd className="font-medium text-vs-text">{subscription.grace_ends_at ?? '—'}</dd></div>
                        </dl>
                    </CardContent>
                </Card>

                <div className="grid gap-4 xl:grid-cols-3">
                    <Card className="border-vs-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-vs-text">Condições</CardTitle>
                            <CardDescription>Valor negociado (vazio segue o plano) e desconto. Valem para as próximas cobranças.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={(event) => { event.preventDefault(); termsForm.patch(terms.url({ subscription: subscription.id }), { preserveScroll: true }); }} className="grid gap-3">
                                <Field id="price" label="Valor (R$)" error={termsForm.errors.price}>
                                    <Input id="price" inputMode="decimal" value={termsForm.data.price} onChange={(event) => termsForm.setData('price', event.target.value)} className="h-10" />
                                </Field>
                                <Field id="discount_percent" label="Desconto (%)" error={termsForm.errors.discount_percent}>
                                    <Input id="discount_percent" type="number" min={0} max={100} value={termsForm.data.discount_percent} onChange={(event) => termsForm.setData('discount_percent', event.target.value)} className="h-10" />
                                </Field>
                                <Field id="discount_until" label="Desconto até" error={termsForm.errors.discount_until}>
                                    <Input id="discount_until" type="date" value={termsForm.data.discount_until} onChange={(event) => termsForm.setData('discount_until', event.target.value)} className="h-10" />
                                </Field>
                                <Button type="submit" disabled={termsForm.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Salvar condições</Button>
                            </form>
                        </CardContent>
                    </Card>

                    {subscription.status === 'trialing' && (
                        <Card className="border-vs-border/70 shadow-sm">
                            <CardHeader>
                                <CardTitle className="text-vs-text">Prorrogar teste</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={(event) => { event.preventDefault(); extendForm.post(extend.url({ subscription: subscription.id }), { preserveScroll: true }); }} className="grid gap-3">
                                    <Field id="days" label="Dias" error={extendForm.errors.days}>
                                        <Input id="days" type="number" min={1} max={90} value={extendForm.data.days} onChange={(event) => extendForm.setData('days', event.target.value)} className="h-10" />
                                    </Field>
                                    <Button type="submit" variant="outline" disabled={extendForm.processing} className="h-10">Prorrogar</Button>
                                </form>
                            </CardContent>
                        </Card>
                    )}

                    {subscription.status !== 'cancelled' && (
                        <Card className="border-vs-border/70 shadow-sm">
                            <CardHeader>
                                <CardTitle className="text-vs-text">Cancelar agora</CardTitle>
                                <CardDescription>Encerra o acesso da empresa imediatamente. Não altera a suspensão administrativa.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={(event) => { event.preventDefault(); cancelForm.post(cancel.url({ subscription: subscription.id }), { preserveScroll: true }); }} className="grid gap-3">
                                    <Field id="cancel-reason" label="Motivo" error={cancelForm.errors.reason}>
                                        <Input id="cancel-reason" value={cancelForm.data.reason} onChange={(event) => cancelForm.setData('reason', event.target.value)} className="h-10" />
                                    </Field>
                                    <Button type="submit" variant="outline" disabled={cancelForm.processing} className="h-10 text-vs-danger">Cancelar assinatura</Button>
                                </form>
                            </CardContent>
                        </Card>
                    )}
                </div>

                <section aria-labelledby="cobrancas" className="grid gap-3">
                    <h2 id="cobrancas" className="text-lg font-semibold text-vs-text">Cobranças</h2>
                    <Card className="overflow-hidden border-vs-border/70 shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[760px] text-left text-sm">
                                <thead className="bg-vs-surface text-vs-muted">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">#</th>
                                        <th className="px-4 py-3 font-medium">Período</th>
                                        <th className="px-4 py-3 font-medium">Valor</th>
                                        <th className="px-4 py-3 font-medium">Situação</th>
                                        <th className="px-4 py-3 font-medium">Efí</th>
                                        <th className="px-4 py-3 font-medium"><span className="sr-only">Ações</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {charges.length === 0 && <tr><td colSpan={6} className="px-4 py-6 text-center text-vs-muted">Nenhuma cobrança.</td></tr>}
                                    {charges.map((charge) => (
                                        <tr key={charge.id} className="border-t border-vs-border/70 align-top">
                                            <td className="px-4 py-3 text-vs-muted">{charge.id}</td>
                                            <td className="px-4 py-3 text-vs-text">{charge.period}<div className="text-vs-muted">vence {charge.due_date} · {charge.method}</div></td>
                                            <td className="px-4 py-3 text-vs-text">R$ {charge.amount}</td>
                                            <td className="px-4 py-3 text-vs-text">
                                                {charge.status_label}
                                                {charge.paid_at && <div className="text-vs-muted">{charge.paid_at} · {charge.paid_via}</div>}
                                                {charge.error && <div className="max-w-xs break-words text-vs-danger">{charge.error}</div>}
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs break-all text-vs-muted">{charge.gateway_id ?? `não emitida (${charge.attempts})`}</td>
                                            <td className="px-4 py-3">
                                                {(charge.status === 'pending' || charge.status === 'waiting') && (
                                                    <div className="flex flex-wrap justify-end gap-2">
                                                        <Button type="button" size="sm" variant="outline" onClick={() => router.post(sync.url({ charge: charge.id }), {}, { preserveScroll: true })}>Sincronizar</Button>
                                                        <SettleCharge charge={charge} />
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </section>

                <section aria-labelledby="historico" className="grid gap-3">
                    <h2 id="historico" className="text-lg font-semibold text-vs-text">Histórico</h2>
                    <Card className="border-vs-border/70 shadow-sm">
                        <CardContent className="pt-6">
                            <ol className="grid gap-3 text-sm">
                                {events.map((event) => (
                                    <li key={event.id} className="border-t border-vs-border/70 pt-2 first:border-t-0 first:pt-0">
                                        <span className="font-medium text-vs-text">{event.at}</span>
                                        <span className="text-vs-muted"> · {event.source}{event.user ? ` (${event.user})` : ''}</span>
                                        {event.from && event.to && event.from !== event.to && <span className="text-vs-text"> · {event.from} → {event.to}</span>}
                                        {event.note && <p className="break-words text-vs-muted">{event.note}</p>}
                                    </li>
                                ))}
                            </ol>
                        </CardContent>
                    </Card>
                </section>
            </div>
        </>
    );
}
