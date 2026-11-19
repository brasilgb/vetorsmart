import { Head, Link, useForm } from '@inertiajs/react';
import { Field } from '@/components/vetor-smart/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as supportIndex, reply as supportReply } from '@/routes/tenant/support';
import { nextActionLabel, statusTone } from './index';

type Ticket = {
    id: number;
    subject: string;
    description: string;
    category_label: string;
    status: string;
    status_label: string;
    is_closed: boolean;
    next_action: string | null;
    opened_by: string | null;
    opened_at: string | null;
    closed_at: string | null;
};

type Event = { id: number; type: string; from: string | null; to: string | null; note: string | null; author: string; from_platform: boolean; at: string | null };

function describe(event: Event): string {
    switch (event.type) {
        case 'opened':
            return 'abriu o chamado';
        case 'reopened':
            return `reabriu o chamado (${event.from ?? ''} → ${event.to ?? ''})`;
        case 'status':
            return `alterou a situação: ${event.from ?? ''} → ${event.to ?? ''}`;
        default:
            return 'respondeu';
    }
}

/** A request of the company, in chronological order, with the replies of the VetorSmart team. */
export default function SupportShow({ ticket, events }: { ticket: Ticket; events: Event[] }) {
    const form = useForm({ note: '' });

    return (
        <>
            <Head title={`Chamado #${ticket.id}`} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Link href={supportIndex.url()} className="text-sm text-vs-link hover:underline">← Suporte</Link>
                    <h1 className="mt-1 text-2xl font-semibold break-words text-vs-text">#{ticket.id} · {ticket.subject}</h1>
                    <div className="mt-2 flex flex-wrap items-center gap-2 text-sm text-vs-muted">
                        <Badge variant="outline" className={statusTone[ticket.status] ?? ''}>{ticket.status_label}</Badge>
                        {ticket.next_action && (
                            <span className={ticket.next_action === 'company' ? 'font-medium text-amber-700 dark:text-amber-400' : ''}>{nextActionLabel[ticket.next_action]} ·</span>
                        )}
                        <span>{ticket.category_label} · aberto por {ticket.opened_by ?? '—'} em {ticket.opened_at}{ticket.closed_at && ` · finalizado em ${ticket.closed_at}`}</span>
                    </div>
                </div>

                <Card className="border-vs-border/70 shadow-sm">
                    <CardHeader><CardTitle className="text-vs-text">Descrição</CardTitle></CardHeader>
                    <CardContent><p className="text-sm whitespace-pre-line break-words text-vs-text">{ticket.description}</p></CardContent>
                </Card>

                <section aria-labelledby="historico" className="grid gap-3">
                    <h2 id="historico" className="text-lg font-semibold text-vs-text">Histórico</h2>
                    <ol className="grid gap-3">
                        {events.map((event) => (
                            <li key={event.id}>
                                <Card className={`p-4 shadow-sm ${event.from_platform ? 'border-vs-blue/40 bg-vs-blue/5 dark:border-sky-400/40 dark:bg-sky-400/10' : 'border-vs-border/70'}`}>
                                    <p className="text-sm">
                                        <span className="font-medium text-vs-text">{event.author}</span>
                                        <span className="text-vs-muted"> {describe(event)} · {event.at}</span>
                                    </p>
                                    {event.note && <p className="mt-1 text-sm whitespace-pre-line break-words text-vs-text">{event.note}</p>}
                                </Card>
                            </li>
                        ))}
                    </ol>
                </section>

                {ticket.is_closed ? (
                    <p role="status" className="rounded-md bg-vs-surface px-3 py-2 text-sm text-vs-text">
                        Este chamado foi encerrado. Se precisar de ajuda de novo, abra um novo chamado na página de Suporte.
                    </p>
                ) : (
                    <Card className="border-vs-border/70 shadow-sm">
                        <CardHeader><CardTitle className="text-vs-text">Responder</CardTitle></CardHeader>
                        <CardContent>
                            <form onSubmit={(event) => { event.preventDefault(); form.post(supportReply.url({ ticket: ticket.id }), { preserveScroll: true, onSuccess: () => form.reset('note') }); }} className="grid gap-3">
                                <Field id="note" label="Sua resposta ou informação complementar" error={form.errors.note}>
                                    <textarea id="note" rows={4} value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} maxLength={5000} className="w-full rounded-md border border-input bg-card px-3 py-2 text-sm text-vs-text" />
                                </Field>
                                {ticket.status === 'resolved' && <p className="text-xs text-vs-muted">Responder reabre o chamado.</p>}
                                <div><Button type="submit" disabled={form.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Enviar</Button></div>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
