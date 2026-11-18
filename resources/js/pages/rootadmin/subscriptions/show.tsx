import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Field } from '@/components/vetor-smart/field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { settle, sync } from '@/routes/rootadmin/charges';
import { cancel as cancelPurchase, confirm as confirmPurchase, refund as refundPurchase } from '@/routes/rootadmin/credits/purchases';
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
    interval: string;
    scheduled_interval: string | null;
    seats: { included: number | null; extra: number; scheduled_extra: number | null; active: number };
    extra_user_price: string | null;
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
    interval: string;
    extra_users: number;
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

type Purchase = { id: number; package: string; credits: number; price: string; status: string; status_label: string; refunded_credits: number; refunded: string; note: string | null; at: string | null };

type Credits = {
    franchise: number | null;
    enforced: boolean;
    included_balance: number;
    purchased_balance: number;
    period: string | null;
    reconciliation: { included_balance: number; included_ledger: number; purchased_balance: number; purchased_ledger: number; consistent: boolean; uncovered_credits: number };
    entries: { id: number; kind_label: string; included_delta: number; purchased_delta: number; credits: number; uncovered: number; operation: string | null; model: string | null; tokens: number | null; cost: string | null; note: string | null; user: string | null; at: string | null }[];
    purchases: Purchase[];
};

function PurchaseAction({ purchase, action, label, url }: { purchase: Purchase; action: string; label: string; url: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });

    if (!open) {
        return <Button type="button" size="sm" variant="outline" onClick={() => setOpen(true)}>{label}</Button>;
    }

    return (
        <form onSubmit={(event) => { event.preventDefault(); form.post(url, { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="grid w-64 gap-2">
            <label htmlFor={`${action}-${purchase.id}`} className="text-xs font-medium text-vs-text">Motivo (registrado na auditoria)</label>
            <Input id={`${action}-${purchase.id}`} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} className="h-9" />
            <InputError message={form.errors.reason ?? (form.errors as Record<string, string>).purchase} />
            <div className="flex gap-2">
                <Button type="submit" size="sm" disabled={form.processing} className="bg-vs-blue text-white hover:bg-vs-blue-hover">{label}</Button>
                <Button type="button" size="sm" variant="ghost" onClick={() => setOpen(false)}>Voltar</Button>
            </div>
        </form>
    );
}

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

export default function SubscriptionShow({ subscription, charges, credits, events }: { subscription: Subscription; charges: Charge[]; credits: Credits; events: Event[] }) {
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
                        <Link href={tenantShow.url({ tenant: subscription.tenant_id })} className="text-vs-link underline underline-offset-2">dados da empresa</Link>
                    </p>
                </div>

                <Card className="border-vs-border/70 shadow-sm">
                    <CardContent className="pt-6">
                        <dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                            <div><dt className="text-vs-muted">Valor do período</dt><dd className="font-medium text-vs-text">{subscription.amount ? `R$ ${subscription.amount}` : 'Plano sem valor definido'}</dd></div>
                            <div><dt className="text-vs-muted">Ciclo</dt><dd className="font-medium text-vs-text">{subscription.interval}{subscription.scheduled_interval && ` → ${subscription.scheduled_interval} no próximo período`}</dd></div>
                            <div>
                                <dt className="text-vs-muted">Vagas de usuários</dt>
                                <dd className="font-medium text-vs-text">
                                    {subscription.seats.included === null
                                        ? `${subscription.seats.active} ativo(s) · sem limite`
                                        : `${subscription.seats.active} de ${subscription.seats.included + subscription.seats.extra} em uso · ${subscription.seats.included} incluída(s) + ${subscription.seats.extra} adicional(is)`}
                                    {subscription.seats.scheduled_extra !== null && ` · ${subscription.seats.scheduled_extra} adicional(is) no próximo período`}
                                </dd>
                            </div>
                            <div><dt className="text-vs-muted">Usuário adicional contratado</dt><dd className="font-medium text-vs-text">{subscription.extra_user_price ? `R$ ${subscription.extra_user_price}/mês` : 'Segue o plano'}</dd></div>
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
                        <div className="overflow-x-auto" tabIndex={0} role="region" aria-label="Tabela de cobranças">
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
                                            <td className="px-4 py-3 text-vs-text">R$ {charge.amount}<div className="text-vs-muted">{charge.interval}{charge.extra_users > 0 && ` · ${charge.extra_users} usuário(s) adicional(is)`}</div></td>
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

                <section aria-labelledby="creditos" className="grid gap-3">
                    <h2 id="creditos" className="text-lg font-semibold text-vs-text">Créditos de IA</h2>
                    <Card className="border-vs-border/70 shadow-sm">
                        <CardContent className="grid gap-4 pt-6">
                            <dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                <div><dt className="text-vs-muted">Franquia mensal</dt><dd className="font-medium text-vs-text">{credits.franchise ?? 'Não definida (não aplicada)'}</dd></div>
                                <div><dt className="text-vs-muted">Saldo da franquia{credits.period && ` (${credits.period})`}</dt><dd className="font-medium text-vs-text">{credits.included_balance}</dd></div>
                                <div><dt className="text-vs-muted">Créditos comprados</dt><dd className="font-medium text-vs-text">{credits.purchased_balance}</dd></div>
                                <div>
                                    <dt className="text-vs-muted">Conciliação com o histórico</dt>
                                    <dd className={credits.reconciliation.consistent ? 'font-medium text-emerald-700 dark:text-emerald-400' : 'font-medium text-vs-danger'}>
                                        {credits.reconciliation.consistent ? 'Consistente' : `Divergente (franquia ${credits.reconciliation.included_balance}/${credits.reconciliation.included_ledger}, comprados ${credits.reconciliation.purchased_balance}/${credits.reconciliation.purchased_ledger})`}
                                        {credits.reconciliation.uncovered_credits > 0 && <span className="block text-vs-muted">{credits.reconciliation.uncovered_credits} crédito(s) consumidos sem saldo</span>}
                                    </dd>
                                </div>
                            </dl>

                            {credits.purchases.length > 0 && (
                                <div className="grid gap-2">
                                    <h3 className="text-sm font-medium text-vs-text">Compras</h3>
                                    <ul className="grid gap-2 text-sm">
                                        {credits.purchases.map((purchase) => (
                                            <li key={purchase.id} className="flex flex-col gap-2 border-t border-vs-border/70 pt-2 md:flex-row md:items-start md:justify-between">
                                                <div className="text-vs-text">
                                                    #{purchase.id} · {purchase.at} · {purchase.package} · {purchase.credits} créditos · R$ {purchase.price} · {purchase.status_label}
                                                    {purchase.refunded_credits > 0 && <span className="text-vs-muted"> · estornados {purchase.refunded_credits} créditos (R$ {purchase.refunded})</span>}
                                                    {purchase.note && <p className="break-words text-vs-muted">{purchase.note}</p>}
                                                </div>
                                                <div className="flex flex-wrap gap-2">
                                                    {purchase.status === 'awaiting_payment' && (
                                                        <>
                                                            <PurchaseAction purchase={purchase} action="confirm" label="Confirmar pagamento" url={confirmPurchase.url({ purchase: purchase.id })} />
                                                            <PurchaseAction purchase={purchase} action="cancel" label="Cancelar compra" url={cancelPurchase.url({ purchase: purchase.id })} />
                                                        </>
                                                    )}
                                                    {(purchase.status === 'paid' || purchase.status === 'partially_refunded') && (
                                                        <PurchaseAction purchase={purchase} action="refund" label="Estornar não usados" url={refundPurchase.url({ purchase: purchase.id })} />
                                                    )}
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            <div className="grid gap-2">
                                <h3 className="text-sm font-medium text-vs-text">Movimentos</h3>
                                <div className="overflow-x-auto" tabIndex={0} role="region" aria-label="Movimentos de créditos de IA">
                                    <table className="w-full min-w-[760px] text-left text-sm">
                                        <thead className="text-vs-muted">
                                            <tr>
                                                <th className="py-2 pr-4 font-medium">Quando</th>
                                                <th className="py-2 pr-4 font-medium">Tipo</th>
                                                <th className="py-2 pr-4 font-medium">Franquia</th>
                                                <th className="py-2 pr-4 font-medium">Comprados</th>
                                                <th className="py-2 pr-4 font-medium">Operação / modelo</th>
                                                <th className="py-2 pr-4 font-medium">Tokens</th>
                                                <th className="py-2 font-medium">Custo estimado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {credits.entries.length === 0 && <tr><td colSpan={7} className="py-4 text-center text-vs-muted">Nenhum movimento.</td></tr>}
                                            {credits.entries.map((entry) => (
                                                <tr key={entry.id} className="border-t border-vs-border/70 align-top text-vs-text">
                                                    <td className="py-2 pr-4">{entry.at}</td>
                                                    <td className="py-2 pr-4">{entry.kind_label}{entry.uncovered > 0 && <span className="block text-vs-danger">{entry.uncovered} sem saldo</span>}{entry.note && <span className="block text-vs-muted">{entry.note}</span>}</td>
                                                    <td className="py-2 pr-4">{entry.included_delta}</td>
                                                    <td className="py-2 pr-4">{entry.purchased_delta}</td>
                                                    <td className="py-2 pr-4">{entry.operation ? `${entry.operation} · ${entry.model}` : '—'}</td>
                                                    <td className="py-2 pr-4">{entry.tokens ?? '—'}</td>
                                                    <td className="py-2">{entry.cost ?? '—'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </CardContent>
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
