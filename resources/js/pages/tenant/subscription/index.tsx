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
import { cancel, contract, resume } from '@/routes/tenant/subscription';
import type { Paginator } from '@/types';

type Summary = {
    plan: string;
    plan_description: string | null;
    status: string;
    status_label: string;
    amount: string | null;
    interval_months: number;
    discount_percent: number;
    next_due: string | null;
    trial_days_left: number | null;
    payment_method: string | null;
    cancel_at_period_end: boolean;
    included_users: number | null;
    can_contract: boolean;
    can_cancel: boolean;
};

type Charge = {
    id: number;
    period: string;
    amount: string;
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

type Props = {
    subscription: Summary | null;
    charges: Paginator<Charge> | null;
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

export default function SubscriptionIndex({ subscription, charges, methods, hasDocument, canManage }: Props) {
    const form = useForm({ method: subscription?.payment_method ?? 'pix', document: '' });

    return (
        <>
            <Head title="Minha Assinatura" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Minha Assinatura" description="Plano, situação financeira, cobranças e documentos de pagamento da sua empresa." />

                {subscription === null ? (
                    <EmptyState>Sua empresa ainda não tem assinatura registrada. A equipe VetorSmart vai ativá-la e avisar você.</EmptyState>
                ) : (
                    <>
                        <Card className="border-vs-border/70 shadow-sm">
                            <CardHeader>
                                <div className="flex flex-wrap items-center gap-3">
                                    <CardTitle className="text-vs-text">{subscription.plan}</CardTitle>
                                    <Badge variant="outline" className={statusTone[subscription.status] ?? ''}>{subscription.status_label}</Badge>
                                </div>
                                {subscription.plan_description && <CardDescription>{subscription.plan_description}</CardDescription>}
                            </CardHeader>
                            <CardContent>
                                <dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <dt className="text-vs-muted">Valor</dt>
                                        <dd className="font-medium text-vs-text">
                                            {subscription.amount === null ? 'A definir' : `${subscription.amount} ${subscription.interval_months === 1 ? 'por mês' : `a cada ${subscription.interval_months} meses`}`}
                                            {subscription.discount_percent > 0 && <span className="ml-1 text-vs-muted">({subscription.discount_percent}% de desconto)</span>}
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
                                        <dt className="text-vs-muted">Usuários incluídos</dt>
                                        <dd className="font-medium text-vs-text">{subscription.included_users ?? 'Sem limite definido'}</dd>
                                    </div>
                                </dl>

                                {subscription.cancel_at_period_end && (
                                    <p role="status" className="mt-4 rounded-md bg-vs-surface px-3 py-2 text-sm text-vs-text">
                                        O cancelamento está agendado para o fim do período atual.
                                    </p>
                                )}
                                {subscription.status === 'suspended' && (
                                    <p role="alert" className="mt-4 rounded-md bg-vs-danger-soft px-3 py-2 text-sm text-vs-danger">
                                        O painel está bloqueado por falta de pagamento. Pague a cobrança em aberto para liberar o acesso.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {canManage && (
                            <Card className="border-vs-border/70 shadow-sm">
                                <CardHeader>
                                    <CardTitle className="text-vs-text">Pagamento</CardTitle>
                                    <CardDescription>
                                        {subscription.can_contract
                                            ? 'Escolha a forma de pagamento e gere a cobrança do período. Ela fica disponível abaixo, com o código Pix ou o boleto.'
                                            : 'O valor do plano ainda está sendo definido. A contratação será liberada em breve; o seu teste continua normalmente.'}
                                    </CardDescription>
                                </CardHeader>
                                {subscription.can_contract && (
                                    <CardContent>
                                        <form
                                            onSubmit={(event) => {
                                                event.preventDefault();
                                                form.post(contract.url(), { preserveScroll: true });
                                            }}
                                            className="grid max-w-xl gap-4"
                                        >
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
                                            {'plan' in form.errors && <p className="text-sm text-vs-danger">{(form.errors as Record<string, string>).plan}</p>}
                                            <div className="flex flex-wrap gap-3">
                                                <Button type="submit" disabled={form.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">
                                                    Gerar cobrança
                                                </Button>
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
                                                        <p className="font-medium text-vs-text">{charge.amount} · {charge.method_label}</p>
                                                        <p className="text-vs-muted">Período {charge.period} · vencimento {charge.due_date}</p>
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
