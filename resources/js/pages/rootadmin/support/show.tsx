import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Field, selectClass } from '@/components/vetor-smart/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as supportIndex, reopen as supportReopen, update as supportUpdate } from '@/routes/rootadmin/support';
import { show as tenantShow } from '@/routes/rootadmin/tenants';
import { nextActionLabel, priorityTone } from './index';

type Option = { value: string; label: string };

type Ticket = {
    id: number;
    subject: string;
    description: string;
    tenant: string | null;
    tenant_id: number | null;
    category_label: string;
    status: string;
    status_label: string;
    next_action: string | null;
    priority: string;
    priority_label: string;
    opened_via: string;
    assignee: string | null;
    assigned_to: number | null;
    due_at: string | null;
    due_at_input: string;
    overdue: boolean;
    is_final: boolean;
    opened_by: string | null;
    opened_at: string | null;
    last_activity_at: string | null;
    closed_at: string | null;
};

type Event = { id: number; type: string; from: string | null; to: string | null; note: string | null; internal: boolean; user: string | null; at: string | null };

const eventLabel: Record<string, string> = {
    opened: 'Chamado aberto',
    comment: 'Resposta',
    internal: 'Nota interna',
    status: 'Situação alterada',
    priority: 'Prioridade alterada',
    assigned: 'Responsável alterado',
    due: 'Prazo alterado',
    reopened: 'Chamado reaberto',
};

/** A request with its full history (internal notes included) and the platform team actions. */
export default function SupportShow({ ticket, events, statuses, priorities, assignees }: { ticket: Ticket; events: Event[]; statuses: Option[]; priorities: Option[]; assignees: { id: number; name: string }[] }) {
    const form = useForm({
        note: '',
        internal: false,
        await_company: true,
        status: ticket.status,
        priority: ticket.priority,
        assigned_to: ticket.assigned_to === null ? '' : String(ticket.assigned_to),
        due_at: ticket.due_at_input,
    });
    const reopenForm = useForm({ reason: '' });

    return (
        <>
            <Head title={`Chamado #${ticket.id} · RootAdmin`} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Link href={supportIndex.url()} className="text-sm text-vs-link hover:underline">← Central de Suporte</Link>
                    <h1 className="mt-1 text-2xl font-semibold break-words text-vs-text">#{ticket.id} · {ticket.subject}</h1>
                    <div className="mt-2 flex flex-wrap items-center gap-2 text-sm text-vs-muted">
                        {ticket.overdue && <Badge variant="outline" className="border-vs-danger text-vs-danger">Atrasado</Badge>}
                        <Badge variant="outline" className={priorityTone[ticket.priority] ?? ''}>{ticket.priority_label}</Badge>
                        <Badge variant="outline" className="border-vs-border text-vs-text">{ticket.status_label}</Badge>
                        {ticket.next_action && <span className="font-medium text-vs-text">{nextActionLabel[ticket.next_action]} ·</span>}
                        <span>
                            {ticket.tenant_id !== null ? <Link href={tenantShow.url({ tenant: ticket.tenant_id })} className="text-vs-link hover:underline">{ticket.tenant}</Link> : 'Plataforma'}
                            {' · '}{ticket.category_label} · {ticket.opened_via === 'tenant' ? 'aberto pela empresa' : 'aberto pela equipe'} ({ticket.opened_by ?? '—'}) em {ticket.opened_at}
                            {' · '}responsável: {ticket.assignee ?? 'ninguém'}{ticket.due_at && ` · prazo ${ticket.due_at}`}{ticket.closed_at && ` · finalizado em ${ticket.closed_at}`}
                        </span>
                    </div>
                </div>

                <Card className="border-vs-border/70 shadow-sm">
                    <CardHeader><CardTitle className="text-vs-text">Descrição</CardTitle></CardHeader>
                    <CardContent><p className="text-sm whitespace-pre-line break-words text-vs-text">{ticket.description}</p></CardContent>
                </Card>

                {ticket.is_final ? (
                    <Card className="border-vs-border/70 shadow-sm">
                        <CardHeader><CardTitle className="text-vs-text">Reabrir chamado</CardTitle></CardHeader>
                        <CardContent>
                            <form onSubmit={(event) => { event.preventDefault(); reopenForm.post(supportReopen.url({ ticket: ticket.id }), { preserveScroll: true }); }} className="grid max-w-xl gap-3">
                                <Field id="reason" label="Motivo (visível para a empresa)" error={reopenForm.errors.reason}>
                                    <Input id="reason" value={reopenForm.data.reason} onChange={(event) => reopenForm.setData('reason', event.target.value)} className="h-10" />
                                </Field>
                                <div><Button type="submit" variant="outline" disabled={reopenForm.processing} className="h-10">Reabrir</Button></div>
                            </form>
                        </CardContent>
                    </Card>
                ) : null}

                <Card className="border-vs-border/70 shadow-sm">
                    <CardHeader><CardTitle className="text-vs-text">Atender</CardTitle></CardHeader>
                    <CardContent>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post(supportUpdate.url({ ticket: ticket.id }), { preserveScroll: true, onSuccess: () => form.reset('note', 'internal', 'await_company') });
                            }}
                            className="grid gap-4 md:grid-cols-2"
                        >
                            <div className="md:col-span-2">
                                <Field id="note" label={form.data.internal ? 'Nota interna (a empresa não vê)' : 'Resposta (a empresa vê)'} error={form.errors.note}>
                                    <textarea id="note" rows={4} value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} maxLength={5000} className="w-full rounded-md border border-input bg-card px-3 py-2 text-sm text-vs-text" />
                                </Field>
                                <div className="mt-2 flex items-center gap-2">
                                    <Checkbox id="internal" checked={form.data.internal} onCheckedChange={(checked) => form.setData('internal', checked === true)} />
                                    <Label htmlFor="internal" className="text-vs-text">Registrar como nota interna</Label>
                                </div>
                                {!form.data.internal && (
                                    <div className="mt-2 flex items-center gap-2">
                                        <Checkbox id="await_company" checked={form.data.await_company} onCheckedChange={(checked) => form.setData('await_company', checked === true)} />
                                        <Label htmlFor="await_company" className="text-vs-text">Aguardar retorno da empresa (passa a próxima ação para a empresa)</Label>
                                    </div>
                                )}
                            </div>
                            <Field id="status" label="Situação" error={form.errors.status}>
                                <select id="status" value={form.data.status} onChange={(event) => form.setData('status', event.target.value)} disabled={ticket.is_final} className={selectClass}>
                                    {statuses.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                </select>
                            </Field>
                            <Field id="priority" label="Prioridade" error={form.errors.priority}>
                                <select id="priority" value={form.data.priority} onChange={(event) => form.setData('priority', event.target.value)} className={selectClass}>
                                    {priorities.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                </select>
                            </Field>
                            <Field id="assigned_to" label="Responsável" error={form.errors.assigned_to}>
                                <select id="assigned_to" value={form.data.assigned_to} onChange={(event) => form.setData('assigned_to', event.target.value)} className={selectClass}>
                                    <option value="">Sem responsável</option>
                                    {assignees.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
                                </select>
                            </Field>
                            <Field id="due_at" label="Prazo de atendimento" error={form.errors.due_at} hint="Horário de Brasília. Vazio: sem prazo.">
                                <Input id="due_at" type="datetime-local" value={form.data.due_at} onChange={(event) => form.setData('due_at', event.target.value)} className="h-10" />
                            </Field>
                            <InputError message={(form.errors as Record<string, string>).reason} />
                            <div className="md:col-span-2"><Button type="submit" disabled={form.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Salvar atendimento</Button></div>
                        </form>
                    </CardContent>
                </Card>

                <section aria-labelledby="historico" className="grid gap-3">
                    <h2 id="historico" className="text-lg font-semibold text-vs-text">Histórico</h2>
                    <Card className="border-vs-border/70 shadow-sm">
                        <CardContent className="pt-6">
                            <ol className="grid gap-3 text-sm">
                                {events.map((event) => (
                                    <li key={event.id} className="border-t border-vs-border/70 pt-2 first:border-t-0 first:pt-0">
                                        <span className="font-medium text-vs-text">{event.at}</span>
                                        <span className="text-vs-muted"> · {eventLabel[event.type] ?? event.type}{event.user ? ` (${event.user})` : ''}</span>
                                        {event.internal && <Badge variant="outline" className="ml-2 border-amber-500 text-amber-700 dark:text-amber-400">Interno</Badge>}
                                        {(event.from || event.to) && event.type !== 'opened' && <span className="text-vs-text"> · {event.from ?? '—'} → {event.to ?? '—'}</span>}
                                        {event.note && <p className="whitespace-pre-line break-words text-vs-text">{event.note}</p>}
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
