import { Head, Link, useForm } from '@inertiajs/react';
import { Field, selectClass } from '@/components/vetor-smart/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as supportIndex, update as supportUpdate } from '@/routes/rootadmin/support';
import { show as tenantShow } from '@/routes/rootadmin/tenants';
import { priorityTone } from './index';

type Option = { value: string; label: string };

type Ticket = {
    id: number;
    subject: string;
    description: string;
    tenant: string | null;
    tenant_id: number | null;
    status: string;
    status_label: string;
    priority: string;
    priority_label: string;
    opened_by: string | null;
    opened_at: string | null;
    last_activity_at: string | null;
    closed_at: string | null;
};

type Event = { id: number; type: string; from: string | null; to: string | null; note: string | null; user: string | null; at: string | null };

const eventLabel: Record<string, string> = { opened: 'Chamado aberto', comment: 'Acompanhamento', status: 'Situação alterada', priority: 'Prioridade alterada' };

/** A support record with its follow-up history. */
export default function SupportShow({ ticket, events, statuses, priorities }: { ticket: Ticket; events: Event[]; statuses: Option[]; priorities: Option[] }) {
    const form = useForm({ note: '', status: ticket.status, priority: ticket.priority });

    return (
        <>
            <Head title={`Chamado #${ticket.id} · RootAdmin`} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Link href={supportIndex.url()} className="text-sm text-vs-link hover:underline">← Suporte</Link>
                    <h1 className="mt-1 text-2xl font-semibold break-words text-vs-text">#{ticket.id} · {ticket.subject}</h1>
                    <div className="mt-2 flex flex-wrap items-center gap-2 text-sm text-vs-muted">
                        <Badge variant="outline" className={priorityTone[ticket.priority] ?? ''}>{ticket.priority_label}</Badge>
                        <Badge variant="outline" className="border-vs-border text-vs-text">{ticket.status_label}</Badge>
                        <span>
                            {ticket.tenant_id !== null ? <Link href={tenantShow.url({ tenant: ticket.tenant_id })} className="text-vs-link hover:underline">{ticket.tenant}</Link> : 'Plataforma'}
                            {' · '}aberto por {ticket.opened_by ?? '—'} em {ticket.opened_at}
                            {ticket.closed_at && ` · finalizado em ${ticket.closed_at}`}
                        </span>
                    </div>
                </div>

                <Card className="border-vs-border/70 shadow-sm">
                    <CardHeader><CardTitle className="text-vs-text">Descrição</CardTitle></CardHeader>
                    <CardContent><p className="text-sm whitespace-pre-line break-words text-vs-text">{ticket.description}</p></CardContent>
                </Card>

                <Card className="border-vs-border/70 shadow-sm">
                    <CardHeader><CardTitle className="text-vs-text">Registrar acompanhamento</CardTitle></CardHeader>
                    <CardContent>
                        <form onSubmit={(event) => { event.preventDefault(); form.post(supportUpdate.url({ ticket: ticket.id }), { preserveScroll: true, onSuccess: () => form.reset('note') }); }} className="grid gap-4 md:grid-cols-2">
                            <div className="md:col-span-2">
                                <Field id="note" label="Acompanhamento" error={form.errors.note}>
                                    <textarea id="note" rows={3} value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} maxLength={5000} className="w-full rounded-md border border-input bg-card px-3 py-2 text-sm text-vs-text" />
                                </Field>
                            </div>
                            <Field id="status" label="Situação" error={form.errors.status}>
                                <select id="status" value={form.data.status} onChange={(event) => form.setData('status', event.target.value)} className={selectClass}>
                                    {statuses.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                </select>
                            </Field>
                            <Field id="priority" label="Prioridade" error={form.errors.priority}>
                                <select id="priority" value={form.data.priority} onChange={(event) => form.setData('priority', event.target.value)} className={selectClass}>
                                    {priorities.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                </select>
                            </Field>
                            <div><Button type="submit" disabled={form.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Registrar</Button></div>
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
                                        {event.from && event.to && <span className="text-vs-text"> · {event.from} → {event.to}</span>}
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
