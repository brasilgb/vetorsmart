import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmAction } from '@/components/vetor-smart/confirm-action';
import { EmptyState } from '@/components/vetor-smart/empty-state';
import { Field, selectClass } from '@/components/vetor-smart/field';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { Pagination } from '@/components/vetor-smart/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cancel, contract, credits as buyCredits, interval as changeInterval, resume, seats as saveSeats } from '@/routes/tenant/subscription';
import type { Paginator } from '@/types';

type NextCharge = {
    interval_label: string;
    base: string;
    discount: string | null;
    extra_users: number;
    extra_user_price: string;
    extra_users_amount: string | null;
    total: string;
    months: number;
    open: boolean;
};

type Summary = {
    plan: string;
    plan_description: string | null;
    status: string;
    status_label: string;
    interval: string;
    interval_label: string;
    scheduled_interval: string | null;
    scheduled_interval_label: string | null;
    next_charge: NextCharge | null;
    price_frozen: boolean;
    discount_percent: number;
    discount_until: string | null;
    next_due: string | null;
    trial_days_left: number | null;
    payment_method: string | null;
    cancel_at_period_end: boolean;
    included_users: number | null;
    active_users: number | null;
    extra_seats: number;
    scheduled_extra_seats: number | null;
    seat_limit: number | null;
    extra_user_price: string | null;
    can_contract: boolean;
    can_cancel: boolean;
};

type Cycle = {
    interval: string;
    label: string;
    months: number;
    total: string;
    monthly_equivalent: string;
    savings_percent: number;
};

type Offer = {
    name: string;
    trial_days: number;
    included_users: number | null;
    extra_user_price: string | null;
    ai_monthly_credits: number | null;
    promotion_percent: number;
    promotion_until: string | null;
    cycles: Cycle[];
    benefits: string[];
};

type Charge = {
    id: number;
    period: string;
    interval_label: string;
    amount: string;
    breakdown: string[];
    due_date: string;
    status: string;
    status_label: string;
    method_label: string;
    paid_at: string | null;
    pix_copy_paste: string | null;
    billet_url: string | null;
    billet_pdf: string | null;
    issue_failed: boolean;
};

type Credits = {
    enforced: boolean;
    franchise: number | null;
    included_balance: number;
    purchased_balance: number;
    total_balance: number;
    used_this_month: number;
    entries: { id: number; kind: string; kind_label: string; credits: number; consumed: number | null; operation: string | null; at: string | null }[];
    purchases_enabled: boolean;
    packages: { id: number; name: string; credits: number; price: string }[];
    purchases: { id: number; package: string; credits: number; price: string; status: string; status_label: string; at: string | null }[];
    has_open_purchase: boolean;
};

type Props = {
    subscription: Summary | null;
    offer: Offer | null;
    charges: Paginator<Charge> | null;
    credits: Credits | null;
    methods: { value: string; label: string }[];
    hasDocument: boolean;
    canManage: boolean;
};

const statusTone: Record<string, string> = {
    trialing: 'border-vs-blue text-vs-blue dark:border-sky-400 dark:text-sky-300',
    active: 'border-emerald-600 text-emerald-700 dark:text-emerald-400',
    past_due: 'border-amber-500 text-amber-700 dark:text-amber-400',
    suspended: 'border-vs-danger text-vs-danger',
    cancelled: 'border-vs-border text-vs-muted',
};

const number = new Intl.NumberFormat('pt-BR');

function CopyButton({ text }: { text: string }) {
    const [copied, setCopied] = useState(false);

    return (
        <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() => {
                void navigator.clipboard?.writeText(text).then(() => setCopied(true));
            }}
        >
            {copied ? 'Copiado' : 'Copiar código Pix'}
        </Button>
    );
}

function SeatsCard({ subscription, canManage }: { subscription: Summary; canManage: boolean }) {
    const form = useForm({ extra_seats: String(subscription.scheduled_extra_seats ?? subscription.extra_seats) });

    if (subscription.included_users === null) {
        return null;
    }

    return (
        <Card className="border-vs-border/70 shadow-sm">
            <CardHeader>
                <CardTitle className="text-vs-text">Vagas de usuários</CardTitle>
                <CardDescription>
                    Cadastrar usuários e contratar vagas são ações separadas. Trocar um usuário por outro dentro das vagas não gera cobrança.
                    Vagas a mais valem na hora e entram nas próximas cobranças geradas, sem valor proporcional; cobranças já abertas não mudam.
                    Reduções valem no próximo período.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                <dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div><dt className="text-vs-muted">Incluídas no plano</dt><dd className="font-medium text-vs-text">{subscription.included_users}</dd></div>
                    <div><dt className="text-vs-muted">Adicionais contratadas</dt><dd className="font-medium text-vs-text">{subscription.extra_seats}{subscription.extra_user_price && ` × ${subscription.extra_user_price}/mês`}</dd></div>
                    <div><dt className="text-vs-muted">Usuários ativos</dt><dd className="font-medium text-vs-text">{subscription.active_users} de {subscription.seat_limit}</dd></div>
                    {subscription.scheduled_extra_seats !== null && (
                        <div><dt className="text-vs-muted">A partir do próximo período</dt><dd className="font-medium text-vs-text">{subscription.scheduled_extra_seats} adicional(is)</dd></div>
                    )}
                </dl>
                {canManage && (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(saveSeats.url(), { preserveScroll: true });
                        }}
                        className="flex flex-wrap items-end gap-3"
                    >
                        <Field id="extra_seats" label="Vagas adicionais" error={form.errors.extra_seats}>
                            <Input id="extra_seats" type="number" min={0} max={500} value={form.data.extra_seats} onChange={(event) => form.setData('extra_seats', event.target.value)} className="h-10 w-32" />
                        </Field>
                        <Button type="submit" variant="outline" disabled={form.processing} className="h-10">Atualizar vagas</Button>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}

function CyclePicker({ cycles, value, onChange, disabled }: { cycles: Cycle[]; value: string; onChange: (value: string) => void; disabled?: boolean }) {
    return (
        <fieldset className="grid gap-3 sm:grid-cols-3" disabled={disabled}>
            <legend className="mb-2 text-sm font-medium text-vs-text">Ciclo de cobrança</legend>
            {cycles.map((cycle) => {
                const selected = value === cycle.interval;

                return (
                    <label
                        key={cycle.interval}
                        className={`flex cursor-pointer flex-col gap-1 rounded-lg border p-4 text-sm transition-colors focus-within:ring-2 focus-within:ring-vs-blue ${selected ? 'border-vs-blue bg-vs-blue/5 dark:bg-sky-400/10' : 'border-vs-border hover:border-vs-blue/60'}`}
                    >
                        <span className="flex items-center justify-between gap-2">
                            <span className="flex items-center gap-2 font-medium text-vs-text">
                                <input type="radio" name="interval" value={cycle.interval} checked={selected} onChange={() => onChange(cycle.interval)} className="accent-vs-blue" />
                                {cycle.label}
                            </span>
                            {cycle.savings_percent > 0 && (
                                <Badge variant="outline" className="border-emerald-600 text-emerald-700 dark:text-emerald-400">−{cycle.savings_percent}%</Badge>
                            )}
                        </span>
                        <span className="text-lg font-semibold text-vs-text">{cycle.total}</span>
                        <span className="text-vs-muted">
                            {cycle.months === 1 ? 'por mês' : `a cada ${cycle.months} meses · equivale a ${cycle.monthly_equivalent}/mês`}
                        </span>
                    </label>
                );
            })}
        </fieldset>
    );
}

export default function SubscriptionIndex({ subscription, offer, charges, credits, methods, hasDocument, canManage }: Props) {
    const form = useForm({ method: subscription?.payment_method ?? 'pix', document: '', interval: subscription?.scheduled_interval ?? subscription?.interval ?? 'monthly' });
    const errors = form.errors as Record<string, string>;
    const intervalChanged = subscription !== null && form.data.interval !== (subscription.scheduled_interval ?? subscription.interval);

    return (
        <>
            <Head title="Minha Assinatura" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Minha Assinatura" description="Plano, ciclo, cobranças, usuários e créditos de inteligência artificial da sua empresa." />

                {subscription === null ? (
                    <EmptyState>Sua empresa ainda não tem assinatura registrada. A equipe VetorSmart vai ativá-la e avisar você.</EmptyState>
                ) : (
                    <>
                        <Card className="border-vs-border/70 shadow-sm">
                            <CardHeader>
                                <div className="flex flex-wrap items-center gap-3">
                                    <CardTitle className="text-vs-text">{subscription.plan}</CardTitle>
                                    <Badge variant="outline" className={statusTone[subscription.status] ?? ''}>{subscription.status_label}</Badge>
                                    <Badge variant="outline" className="border-vs-border text-vs-text">Ciclo {subscription.interval_label}</Badge>
                                </div>
                                {subscription.plan_description && <CardDescription>{subscription.plan_description}</CardDescription>}
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <dt className="text-vs-muted">Próxima cobrança</dt>
                                        <dd className="font-medium text-vs-text">
                                            {subscription.next_charge === null ? 'A definir' : `${subscription.next_charge.total} (${subscription.next_charge.interval_label})`}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-vs-muted">{subscription.status === 'trialing' ? 'Fim do teste' : subscription.status === 'past_due' ? 'Prazo para regularizar' : 'Próximo vencimento'}</dt>
                                        <dd className="font-medium text-vs-text">{subscription.next_due ?? '—'}</dd>
                                    </div>
                                    {subscription.trial_days_left !== null && (
                                        <div>
                                            <dt className="text-vs-muted">Dias restantes do teste</dt>
                                            <dd className="font-medium text-vs-text">{subscription.trial_days_left}</dd>
                                        </div>
                                    )}
                                    <div>
                                        <dt className="text-vs-muted">Usuários</dt>
                                        <dd className="font-medium text-vs-text">
                                            {subscription.active_users ?? '—'} ativo(s) · {subscription.included_users === null ? 'sem limite definido' : `${subscription.included_users} incluído(s)`}
                                        </dd>
                                    </div>
                                </dl>

                                {subscription.next_charge && (
                                    <div className="rounded-md border border-vs-border/70 p-3 text-sm">
                                        <p className="mb-2 font-medium text-vs-text">{subscription.next_charge.open ? 'Composição da cobrança em aberto' : 'Composição da próxima cobrança'}</p>
                                        <ul className="grid gap-1 text-vs-text">
                                            <li className="flex justify-between gap-4"><span>Plano {subscription.next_charge.interval_label}</span><span>{subscription.next_charge.base}</span></li>
                                            {subscription.next_charge.discount && (
                                                <li className="flex justify-between gap-4">
                                                    <span>Desconto de {subscription.discount_percent}%{subscription.discount_until && ` até ${subscription.discount_until}`}</span>
                                                    <span>−{subscription.next_charge.discount}</span>
                                                </li>
                                            )}
                                            {subscription.next_charge.extra_users > 0 && (
                                                <li className="flex justify-between gap-4">
                                                    <span>{subscription.next_charge.extra_users} vaga(s) adicional(is) × {subscription.next_charge.extra_user_price}/mês{subscription.next_charge.months > 1 && ` × ${subscription.next_charge.months} meses`}</span>
                                                    <span>{subscription.next_charge.extra_users_amount}</span>
                                                </li>
                                            )}
                                            <li className="mt-1 flex justify-between gap-4 border-t border-vs-border/70 pt-1 font-semibold"><span>Total</span><span>{subscription.next_charge.total}</span></li>
                                        </ul>
                                    </div>
                                )}

                                {subscription.scheduled_interval_label && (
                                    <p role="status" className="rounded-md bg-vs-surface px-3 py-2 text-sm text-vs-text">
                                        Troca para o ciclo {subscription.scheduled_interval_label} agendada: vale a partir do próximo período.
                                    </p>
                                )}
                                {subscription.cancel_at_period_end && (
                                    <p role="status" className="rounded-md bg-vs-surface px-3 py-2 text-sm text-vs-text">
                                        O cancelamento está agendado para o fim do período atual. O acesso continua até lá.
                                    </p>
                                )}
                                {subscription.status === 'suspended' && (
                                    <p role="alert" className="rounded-md bg-vs-danger-soft px-3 py-2 text-sm text-vs-danger">
                                        O painel está bloqueado por falta de pagamento. Pague a cobrança em aberto para liberar o acesso.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {canManage && (
                            <Card className="border-vs-border/70 shadow-sm">
                                <CardHeader>
                                    <CardTitle className="text-vs-text">Gerenciar assinatura</CardTitle>
                                    <CardDescription>
                                        {subscription.can_contract
                                            ? 'Escolha o ciclo e a forma de pagamento e gere a cobrança do período. Ela fica disponível abaixo, com o código Pix ou o boleto.'
                                            : 'O valor do plano ainda está sendo definido. A contratação será liberada em breve; o seu teste continua normalmente.'}
                                    </CardDescription>
                                </CardHeader>
                                {subscription.can_contract && offer && (
                                    <CardContent>
                                        <form
                                            onSubmit={(event) => {
                                                event.preventDefault();
                                                form.post(contract.url(), { preserveScroll: true });
                                            }}
                                            className="grid gap-4"
                                        >
                                            <CyclePicker cycles={offer.cycles} value={form.data.interval} onChange={(value) => form.setData('interval', value)} />
                                            {errors.interval && <p className="text-sm text-vs-danger">{errors.interval}</p>}
                                            <div className="grid max-w-xl gap-4">
                                                <Field id="method" label="Forma de pagamento" error={form.errors.method}>
                                                    <select id="method" value={form.data.method} onChange={(event) => form.setData('method', event.target.value)} className={selectClass}>
                                                        {methods.map((method) => (
                                                            <option key={method.value} value={method.value}>{method.label}</option>
                                                        ))}
                                                    </select>
                                                </Field>
                                                {form.data.method === 'boleto' && !hasDocument && (
                                                    <Field id="document" label="CPF ou CNPJ da empresa" error={form.errors.document} hint="Obrigatório no boleto.">
                                                        <Input id="document" value={form.data.document} onChange={(event) => form.setData('document', event.target.value)} inputMode="numeric" autoComplete="off" className="h-10" />
                                                    </Field>
                                                )}
                                            </div>
                                            {errors.plan && <p className="text-sm text-vs-danger">{errors.plan}</p>}
                                            <div className="flex flex-wrap gap-3">
                                                <Button type="submit" disabled={form.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">
                                                    Gerar cobrança
                                                </Button>
                                                {intervalChanged && (
                                                    <Button type="button" variant="outline" className="h-10" disabled={form.processing} onClick={() => router.post(changeInterval.url(), { interval: form.data.interval }, { preserveScroll: true })}>
                                                        Só trocar o ciclo
                                                    </Button>
                                                )}
                                                {subscription.can_cancel && (
                                                    <ConfirmAction
                                                        title="Cancelar a assinatura?"
                                                        description="O acesso continua até o fim do período atual. Depois disso, o painel fica bloqueado."
                                                        confirmLabel="Agendar cancelamento"
                                                        onConfirm={() => router.post(cancel.url(), {}, { preserveScroll: true })}
                                                        trigger={<Button type="button" variant="outline" className="h-10">Cancelar assinatura</Button>}
                                                    />
                                                )}
                                                {subscription.cancel_at_period_end && (
                                                    <Button type="button" variant="outline" className="h-10" onClick={() => router.post(resume.url(), {}, { preserveScroll: true })}>
                                                        Desfazer cancelamento
                                                    </Button>
                                                )}
                                            </div>
                                        </form>
                                    </CardContent>
                                )}
                            </Card>
                        )}

                        <SeatsCard subscription={subscription} canManage={canManage} />

                        {offer && (
                            <Card className="border-vs-border/70 shadow-sm">
                                <CardHeader>
                                    <CardTitle className="text-vs-text">Condições do plano</CardTitle>
                                    <CardDescription>O que está incluído e como funcionam descontos, trocas e cancelamento.</CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4 text-sm text-vs-text md:grid-cols-2">
                                    <ul className="grid list-disc gap-1 pl-5">
                                        {offer.benefits.map((benefit) => <li key={benefit}>{benefit}</li>)}
                                    </ul>
                                    <ul className="grid list-disc gap-1 pl-5">
                                        <li>{offer.trial_days} dias gratuitos para novas empresas.</li>
                                        {offer.included_users !== null && <li>{offer.included_users} usuários incluídos em qualquer ciclo{offer.extra_user_price && `; cada usuário adicional custa ${offer.extra_user_price} por mês`}.</li>}
                                        <li>Semestral e anual têm desconto sobre o mensal; o valor do ciclo é cobrado de uma vez.</li>
                                        {offer.promotion_percent > 0 && <li>Promoção: {offer.promotion_percent}% de desconto{offer.promotion_until && ` para contratações até ${offer.promotion_until}`}.</li>}
                                        <li>O valor contratado fica garantido: mudanças de preço não alteram cobranças já contratadas.</li>
                                        <li>A troca de ciclo vale a partir do próximo período, sem cobrança proporcional.</li>
                                        <li>No cancelamento, o acesso continua até o fim do período pago.</li>
                                        {offer.ai_monthly_credits !== null && <li>{number.format(offer.ai_monthly_credits)} créditos de IA por mês, renovados mensalmente em todos os ciclos.</li>}
                                    </ul>
                                </CardContent>
                            </Card>
                        )}

                        {credits && (
                            <Card className="border-vs-border/70 shadow-sm">
                                <CardHeader>
                                    <CardTitle className="text-vs-text">Créditos de inteligência artificial</CardTitle>
                                    <CardDescription>
                                        Usados pelas respostas automáticas com IA. Sem saldo, só elas pausam: o atendimento convencional continua normalmente.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-5">
                                    <dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                        <div>
                                            <dt className="text-vs-muted">Franquia mensal</dt>
                                            <dd className="font-medium text-vs-text">{credits.franchise === null ? 'A definir' : number.format(credits.franchise)}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-vs-muted">Saldo da franquia</dt>
                                            <dd className="font-medium text-vs-text">{number.format(credits.included_balance)}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-vs-muted">Créditos comprados</dt>
                                            <dd className="font-medium text-vs-text">{number.format(credits.purchased_balance)} <span className="text-vs-muted">(não expiram)</span></dd>
                                        </div>
                                        <div>
                                            <dt className="text-vs-muted">Consumo neste mês</dt>
                                            <dd className="font-medium text-vs-text">{number.format(credits.used_this_month)}</dd>
                                        </div>
                                    </dl>

                                    {credits.enforced && credits.total_balance <= 0 && (
                                        <p role="alert" className="rounded-md bg-vs-danger-soft px-3 py-2 text-sm text-vs-danger">
                                            Créditos esgotados: as respostas com IA estão pausadas até a renovação da franquia ou a compra de créditos.
                                        </p>
                                    )}

                                    <div className="grid gap-2">
                                        <h3 className="text-sm font-medium text-vs-text">Comprar créditos adicionais</h3>
                                        {!credits.purchases_enabled ? (
                                            <p className="text-sm text-vs-muted">A compra de créditos adicionais será liberada em breve.</p>
                                        ) : credits.packages.length === 0 ? (
                                            <p className="text-sm text-vs-muted">Nenhum pacote disponível no momento.</p>
                                        ) : (
                                            <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                                {credits.packages.map((pack) => (
                                                    <li key={pack.id} className="flex flex-col gap-2 rounded-lg border border-vs-border/70 p-3 text-sm">
                                                        <span className="font-medium text-vs-text">{pack.name}</span>
                                                        <span className="text-vs-muted">{number.format(pack.credits)} créditos · {pack.price}</span>
                                                        {canManage && (
                                                            <ConfirmAction
                                                                title={`Comprar ${pack.name}?`}
                                                                description={`${number.format(pack.credits)} créditos por ${pack.price}, cobrados à parte da assinatura. Os créditos entram no saldo após a confirmação do pagamento e não expiram.`}
                                                                confirmLabel="Confirmar compra"
                                                                onConfirm={() => router.post(buyCredits.url(), { package_id: pack.id }, { preserveScroll: true })}
                                                                trigger={<Button type="button" size="sm" variant="outline" disabled={credits.has_open_purchase}>Comprar</Button>}
                                                            />
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                        {credits.has_open_purchase && <p className="text-sm text-vs-muted">Há uma compra aguardando pagamento.</p>}
                                    </div>

                                    {credits.purchases.length > 0 && (
                                        <div className="grid gap-2">
                                            <h3 className="text-sm font-medium text-vs-text">Compras de créditos</h3>
                                            <ul className="grid gap-1 text-sm">
                                                {credits.purchases.map((purchase) => (
                                                    <li key={purchase.id} className="flex flex-wrap justify-between gap-2 border-b border-vs-border/50 py-1 text-vs-text">
                                                        <span>{purchase.at} · {purchase.package} · {number.format(purchase.credits)} créditos · {purchase.price}</span>
                                                        <span className="text-vs-muted">{purchase.status_label}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}

                                    <div className="grid gap-2">
                                        <h3 className="text-sm font-medium text-vs-text">Histórico de consumo</h3>
                                        {credits.entries.length === 0 ? (
                                            <p className="text-sm text-vs-muted">Nenhum movimento ainda.</p>
                                        ) : (
                                            <ul className="grid gap-1 text-sm">
                                                {credits.entries.map((entry) => (
                                                    <li key={entry.id} className="flex flex-wrap justify-between gap-2 border-b border-vs-border/50 py-1 text-vs-text">
                                                        <span>{entry.at} · {entry.kind_label}</span>
                                                        <span className={entry.credits < 0 ? 'text-vs-text' : 'text-emerald-700 dark:text-emerald-400'}>
                                                            {entry.consumed !== null ? `−${number.format(entry.consumed)}` : `${entry.credits > 0 ? '+' : ''}${number.format(entry.credits)}`}
                                                        </span>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        <section aria-labelledby="historico" className="grid gap-3">
                            <h2 id="historico" className="text-lg font-semibold text-vs-text">Histórico de cobranças</h2>
                            {charges === null || charges.data.length === 0 ? (
                                <EmptyState>Nenhuma cobrança gerada ainda.</EmptyState>
                            ) : (
                                <ul className="grid gap-3">
                                    {charges.data.map((charge) => (
                                        <li key={charge.id}>
                                            <Card className="border-vs-border/70 p-4 shadow-sm">
                                                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                                    <div className="grid gap-1 text-sm">
                                                        <p className="font-medium text-vs-text">{charge.amount} · {charge.interval_label} · {charge.method_label}</p>
                                                        <p className="text-vs-muted">Período {charge.period} · vencimento {charge.due_date}</p>
                                                        {charge.breakdown.length > 0 && <p className="text-vs-muted">{charge.breakdown.join(' · ')}</p>}
                                                        <p className="text-vs-muted">
                                                            {charge.status_label}
                                                            {charge.paid_at && ` em ${charge.paid_at}`}
                                                            {charge.issue_failed && ' · a emissão falhou e será tentada de novo automaticamente'}
                                                        </p>
                                                        {charge.pix_copy_paste && <p className="max-w-xl font-mono text-xs break-all text-vs-text">{charge.pix_copy_paste}</p>}
                                                    </div>
                                                    <div className="flex shrink-0 flex-wrap gap-2">
                                                        {charge.pix_copy_paste && <CopyButton text={charge.pix_copy_paste} />}
                                                        {charge.billet_url && (
                                                            <Button asChild size="sm" variant="outline">
                                                                <a href={charge.billet_url} target="_blank" rel="noopener noreferrer">Abrir boleto</a>
                                                            </Button>
                                                        )}
                                                        {charge.billet_pdf && (
                                                            <Button asChild size="sm" variant="outline">
                                                                <a href={charge.billet_pdf} target="_blank" rel="noopener noreferrer">PDF</a>
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>
                                            </Card>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {charges && <Pagination links={charges.links} />}
                        </section>
                    </>
                )}
            </div>
        </>
    );
}
