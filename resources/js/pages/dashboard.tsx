import { Head, Link, useForm } from '@inertiajs/react';
import { CheckCircle2, Circle } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Field } from '@/components/vetor-smart/field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

type Actions = Partial<Record<'triage' | 'schedule' | 'start' | 'complete' | 'amount' | 'cancel', string>>;

type Item = {
    id: number;
    title: string;
    detail: string;
    status?: string;
    status_label?: string;
    assignee?: string | null;
    since?: string | null;
    href: string;
    actions?: Actions;
    // Appointments
    appointment_id?: number;
    when?: string;
    ends?: string;
    professional?: string | null;
    agenda?: string;
    // Assistant conversations
    assume?: string | null;
};

type Queue = { key: string; title: string; hint: string; tone: 'attention' | 'warning' | 'info'; count: number; items: Item[]; href: string };

type Props = {
    metrics: { customers: number; services: number; professionals: number };
    work: { queues: Queue[]; today: Queue; upcoming: Queue; summary: { completed: number; completed_today: number; cancelled: number; open: number } };
    checklist: { key: string; label: string; done: boolean }[];
    needsSetup: boolean;
};

const toneBorder: Record<Queue['tone'], string> = {
    attention: 'border-l-amber-500 dark:border-l-amber-400',
    warning: 'border-l-red-600 dark:border-l-red-400',
    info: 'border-l-vs-blue dark:border-l-sky-400',
};

/** The first validation message of a form, whatever field the server attached it to. */
function firstError(errors: object): string | undefined {
    return Object.values(errors as Record<string, unknown>).find((value): value is string => typeof value === 'string' && value !== '');
}

const actionButton ='inline-flex h-10 items-center justify-center rounded-md px-3 text-sm font-medium';
const primary = `${actionButton} bg-vs-blue text-white hover:bg-vs-blue-hover disabled:opacity-60`;
const secondary = `${actionButton} border border-vs-border bg-card text-vs-text hover:border-vs-blue disabled:opacity-60`;

/** Work center of the company: what needs action now, with the actions the member may take. */
export default function Dashboard({ metrics, work, checklist, needsSetup }: Props) {
    const nothingPending = work.queues.length === 0;

    return (
        <>
            <Head title="Painel" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-vs-text">Central de trabalho</h1>
                    <p className="text-sm text-vs-muted">O que precisa de você agora, com os atalhos para resolver.</p>
                </div>

                <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4" aria-label="Resumo">
                    {[
                        { label: 'Em aberto', value: work.summary.open, href: '/solicitacoes' },
                        { label: 'Visitas hoje', value: work.today.count, href: work.today.href },
                        { label: 'Concluídos hoje', value: work.summary.completed_today, href: null },
                        { label: 'Concluídos (30 dias)', value: work.summary.completed, href: '/solicitacoes?status=completed' },
                    ].map((item) => (
                        <div key={item.label} className="rounded-xl border border-vs-border/70 bg-card p-3 shadow-sm">
                            <dt className="text-xs text-vs-muted">{item.label}</dt>
                            <dd className="text-2xl font-semibold tabular-nums text-vs-text">
                                {item.href ? <Link href={item.href} className="hover:underline">{item.value}</Link> : item.value}
                            </dd>
                        </div>
                    ))}
                </dl>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                    <section aria-labelledby="pendencias" className="grid content-start gap-4">
                        <h2 id="pendencias" className="text-lg font-semibold text-vs-text">Pendências</h2>
                        {nothingPending ? (
                            <Card className="border-vs-border/70 p-5 text-sm text-vs-muted shadow-sm">
                                Nada pendente agora: nenhuma solicitação aguardando triagem ou agendamento, nada atrasado ou parado, e nenhuma conversa esperando a equipe.
                            </Card>
                        ) : (
                            work.queues.map((queue) => <QueueCard key={queue.key} queue={queue} />)
                        )}
                    </section>

                    <section aria-labelledby="agenda-hoje" className={`grid content-start gap-4 ${work.today.count > 0 ? 'order-first xl:order-none' : ''}`}>
                        <h2 id="agenda-hoje" className="sr-only">Agenda</h2>
                        <QueueCard queue={work.today} empty="Nenhuma visita marcada para hoje." />
                        <QueueCard queue={work.upcoming} empty="Nenhuma visita nos próximos dias." />
                    </section>
                </div>

                <p className="text-xs text-vs-muted">
                    Cadastro: {metrics.customers} clientes ativos · {metrics.services} serviços ativos · {metrics.professionals} profissionais ativos · {work.summary.cancelled} cancelados ou não realizados nos últimos 30 dias.
                </p>

                {needsSetup && (
                    <Card className="border-vs-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-vs-text">Configure sua empresa para começar</CardTitle>
                            <CardDescription>Você pode concluir estes passos depois. Nada bloqueia o acesso aos demais recursos.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="grid gap-3">
                                {checklist.map((item) => (
                                    <li key={item.key} className="flex items-center gap-3 text-sm text-vs-text">
                                        {item.done ? <CheckCircle2 className="size-5 text-vs-cyan" aria-label="Concluído" /> : <Circle className="size-5 text-vs-muted" aria-label="Pendente" />}
                                        <span className={item.done ? 'text-vs-muted line-through' : ''}>{item.label}</span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function QueueCard({ queue, empty }: { queue: Queue; empty?: string }) {
    return (
        <Card className={`gap-0 border-l-4 border-vs-border/70 p-0 shadow-sm ${toneBorder[queue.tone]}`}>
            <div className="flex items-start justify-between gap-3 p-4 pb-2">
                <div>
                    <h3 className="font-semibold text-vs-text">
                        {queue.title} <span className="ml-1 rounded-full bg-vs-surface px-2 py-0.5 text-xs font-semibold tabular-nums text-vs-text">{queue.count}</span>
                    </h3>
                    <p className="text-xs text-vs-muted">{queue.hint}</p>
                </div>
                {queue.count > queue.items.length && (
                    <Link href={queue.href} className="inline-flex min-h-10 shrink-0 items-center text-sm font-medium text-vs-link hover:underline">Ver todas</Link>
                )}
            </div>
            {queue.items.length === 0 ? (
                <p className="px-4 pb-4 text-sm text-vs-muted">{empty ?? 'Nada aqui.'}</p>
            ) : (
                <ul className="divide-y divide-vs-border/70">
                    {queue.items.map((item) => (
                        <li key={`${queue.key}-${item.appointment_id ?? item.id}`} className="grid gap-2 px-4 py-3">
                            <div className="min-w-0">
                                <Link href={item.href} className="font-medium text-vs-text hover:underline">
                                    {item.when ? `${item.when}–${item.ends} · ` : ''}{item.title}
                                </Link>
                                <p className="text-sm text-vs-muted">
                                    {[item.detail, item.professional, item.assignee ? `Resp.: ${item.assignee}` : null, item.when ? null : item.since].filter(Boolean).join(' · ')}
                                </p>
                            </div>
                            <ItemActions item={item} />
                        </li>
                    ))}
                </ul>
            )}
            {queue.count > 0 && queue.count <= queue.items.length && (
                <Link href={queue.href} className="flex min-h-11 items-center px-4 text-sm font-medium text-vs-link hover:underline">Abrir lista</Link>
            )}
        </Card>
    );
}

/**
 * The quick actions of an item. Each one calls the existing route; the server validates the status again, so a
 * stale screen or a double tap never moves a request to an invalid state (the error is shown next to the action).
 */
function ItemActions({ item }: { item: Item }) {
    const actions = item.actions ?? {};
    const start = useForm({});
    const assume = useForm({});

    return (
        <div className="flex flex-wrap items-center gap-2">
            {item.assume && (
                <button type="button" className={primary} disabled={assume.processing} onClick={() => assume.post(item.assume as string)}>Assumir atendimento</button>
            )}
            {actions.triage && <Link href={actions.triage} className={primary}>Fazer triagem</Link>}
            {actions.schedule && <Link href={actions.schedule} className={primary}>Agendar</Link>}
            {actions.start && (
                <button type="button" className={primary} disabled={start.processing} onClick={() => start.post(actions.start as string, { preserveScroll: true })}>Iniciar execução</button>
            )}
            {actions.complete && <AmountDialog url={actions.complete} method="post" label="Concluir" title="Concluir atendimento" description="Informe o valor que a empresa recebeu, se já souber. Pode ficar em branco e ser informado depois." primaryAction />}
            {actions.amount && <AmountDialog url={actions.amount} method="patch" label="Registrar valor" title="Registrar valor realizado" description="Valor que a empresa recebeu por este serviço. Fica registrado no histórico." primaryAction />}
            {item.agenda && item.status === 'scheduled' && <Link href={item.agenda} className={secondary}>Remarcar</Link>}
            {!item.assume && <Link href={item.href} className={secondary}>Abrir</Link>}
            {actions.cancel && <CancelDialog url={actions.cancel} />}
            {[firstError(start.errors), firstError(assume.errors)].filter(Boolean).map((message) => (
                <p key={message} role="alert" className="w-full text-sm text-vs-danger">{message}</p>
            ))}
        </div>
    );
}

function AmountDialog({ url, method, label, title, description, primaryAction = false }: { url: string; method: 'post' | 'patch'; label: string; title: string; description: string; primaryAction?: boolean }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ realized_amount: '' });
    const error = firstError(form.errors);

    return (
        <ActionDialog open={open} setOpen={setOpen} trigger={<button type="button" className={primaryAction ? primary : secondary}>{label}</button>}>
            <form
                className="grid gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.submit(method, url, { preserveScroll: true, onSuccess: () => { setOpen(false); form.reset(); } });
                }}
            >
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description} Não é comprovante de pagamento.</DialogDescription>
                </DialogHeader>
                <Field id={`amount-${url}`} label="Valor realizado (R$)" error={error} hint="Por exemplo 1.250,00.">
                    <Input id={`amount-${url}`} inputMode="decimal" autoComplete="off" maxLength={20} value={form.data.realized_amount} onChange={(event) => form.setData('realized_amount', event.target.value)} />
                </Field>
                <DialogFooter>
                    <Button type="button" variant="outline" className="h-10" onClick={() => setOpen(false)}>Voltar</Button>
                    <Button type="submit" className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover" disabled={form.processing || (method === 'patch' && form.data.realized_amount.trim() === '')}>{label}</Button>
                </DialogFooter>
            </form>
        </ActionDialog>
    );
}

function CancelDialog({ url }: { url: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });

    return (
        <ActionDialog open={open} setOpen={setOpen} trigger={<button type="button" className={`${actionButton} text-vs-danger hover:bg-vs-danger-soft`}>Cancelar</button>}>
            <form
                className="grid gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(url, { preserveScroll: true, onSuccess: () => { setOpen(false); form.reset(); } });
                }}
            >
                <DialogHeader>
                    <DialogTitle>Cancelar solicitação?</DialogTitle>
                    <DialogDescription>Um agendamento ativo também é cancelado. O motivo fica no histórico e aparece em Meu Crescimento.</DialogDescription>
                </DialogHeader>
                <Field id={`reason-${url}`} label="Motivo do cancelamento" error={firstError(form.errors)}>
                    <textarea
                        id={`reason-${url}`}
                        rows={3}
                        maxLength={500}
                        required
                        value={form.data.reason}
                        onChange={(event) => form.setData('reason', event.target.value)}
                        className="w-full rounded-md border border-input bg-card p-3 text-sm text-vs-text focus-visible:ring-[3px] focus-visible:ring-vs-blue/30 focus-visible:outline-none"
                    />
                </Field>
                <DialogFooter>
                    <Button type="button" variant="outline" className="h-10" onClick={() => setOpen(false)}>Voltar</Button>
                    <Button type="submit" className="h-10 bg-red-600 text-white hover:bg-red-700" disabled={form.processing || form.data.reason.trim() === ''}>Cancelar solicitação</Button>
                </DialogFooter>
            </form>
        </ActionDialog>
    );
}

function ActionDialog({ open, setOpen, trigger, children }: { open: boolean; setOpen: (open: boolean) => void; trigger: ReactNode; children: ReactNode }) {
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>{children}</DialogContent>
        </Dialog>
    );
}
