import { Head, Link } from '@inertiajs/react';
import { Mail, MessageCircle, Phone } from 'lucide-react';
import { EmptyState } from '@/components/vetor-smart/empty-state';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { Pagination } from '@/components/vetor-smart/pagination';
import { StatusBadge } from '@/components/vetor-smart/status-badge';
import { Card } from '@/components/ui/card';
import { edit, index as customersIndex } from '@/routes/tenant/customers';
import { show as requestShow } from '@/routes/tenant/requests';
import type { Paginator } from '@/types';

type HistoryItem = {
    id: number;
    service: string | null;
    priority: string;
    status: string;
    professional: string | null;
    created_at: string;
    closed_at: string | null;
    closing_reason: string | null;
    appointments: { starts_at: string; professional: string | null; status: string; cancelling_reason: string | null; cancelled_by: string | null; cancelled_at: string | null }[];
    last_event: { note: string | null; user: string | null; at: string } | null;
};

type Props = {
    customer: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
        whatsapp: string | null;
        document: string | null;
        address: string | null;
        city: string | null;
        state: string | null;
        notes: string | null;
        status: string;
        first_contact: string | null;
        contact: { kind: 'phone' | 'whatsapp' | 'email'; label: string; href: string }[];
    };
    summary: {
        requests: number;
        completed: number;
        cancelled: number;
        open: number;
        revenue: string | null;
        revenue_known: number;
        revenue_unknown: number;
        last_service: { date: string; service: string | null } | null;
        frequency_days: number | null;
        recurring: boolean;
    };
    financial: boolean;
    upcoming: { id: number; request_id: number; starts_at: string; service: string | null; professional: string | null }[];
    history: Paginator<HistoryItem>;
};

const contactIcon = { phone: Phone, whatsapp: MessageCircle, email: Mail } as const;

/** The customer's page: who it is, how to reach, what was done and what is ahead. */
export default function CustomerShow({ customer, summary, financial, upcoming, history }: Props) {
    const location = [customer.address, customer.city, customer.state].filter(Boolean).join(' · ');

    return (
        <>
            <Head title={customer.name} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={customer.name}
                    description={customer.first_contact ? `Cliente desde ${customer.first_contact}.` : 'Ficha do cliente.'}
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Link href={edit.url({ customer: customer.id })} className="inline-flex h-10 items-center rounded-md border border-vs-border bg-card px-4 text-sm font-medium text-vs-text hover:border-vs-blue">Editar dados</Link>
                            <Link href={customersIndex.url()} className="inline-flex h-10 items-center px-2 text-sm font-medium text-vs-link hover:underline">Voltar à carteira</Link>
                        </div>
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    <StatusBadge active={customer.status === 'active'} />
                    {summary.recurring && <span className="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Recorrente</span>}
                    {summary.open > 0 && <span className="rounded-full bg-vs-blue/10 px-2.5 py-0.5 text-xs font-medium text-vs-link">{summary.open} em aberto</span>}
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                    <Card className="grid content-start gap-4 border-vs-border/70 p-5 shadow-sm">
                        <h2 className="text-base font-semibold text-vs-text">Contato</h2>
                        {customer.contact.length === 0 ? (
                            <p className="text-sm text-vs-muted">Sem telefone ou e-mail cadastrado. <Link href={edit.url({ customer: customer.id })} className="text-vs-link hover:underline">Completar dados</Link>.</p>
                        ) : (
                            <div className="flex flex-wrap gap-2">
                                {customer.contact.map((item) => {
                                    const Icon = contactIcon[item.kind];

                                    return (
                                        <a key={item.kind} href={item.href} target={item.kind === 'whatsapp' ? '_blank' : undefined} rel={item.kind === 'whatsapp' ? 'noopener noreferrer' : undefined} className="inline-flex h-11 items-center gap-2 rounded-md bg-vs-blue px-4 text-sm font-medium text-white hover:bg-vs-blue-hover">
                                            <Icon className="size-4" aria-hidden="true" /> {item.label}
                                        </a>
                                    );
                                })}
                            </div>
                        )}
                        <dl className="grid gap-2 text-sm">
                            {customer.phone && <div><dt className="text-vs-muted">Telefone</dt><dd className="text-vs-text">{customer.phone}</dd></div>}
                            {customer.whatsapp && customer.whatsapp !== customer.phone && <div><dt className="text-vs-muted">WhatsApp</dt><dd className="text-vs-text">{customer.whatsapp}</dd></div>}
                            {customer.email && <div><dt className="text-vs-muted">E-mail</dt><dd className="break-all text-vs-text">{customer.email}</dd></div>}
                            {location && <div><dt className="text-vs-muted">Endereço</dt><dd className="text-vs-text">{location}</dd></div>}
                            {customer.notes && <div><dt className="text-vs-muted">Observações</dt><dd className="whitespace-pre-line text-vs-text">{customer.notes}</dd></div>}
                        </dl>
                    </Card>

                    <Card className="grid content-start gap-4 border-vs-border/70 p-5 shadow-sm">
                        <h2 className="text-base font-semibold text-vs-text">Resumo</h2>
                        <dl className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                            <div><dt className="text-vs-muted">Serviços concluídos</dt><dd className="text-2xl font-semibold tabular-nums text-vs-text">{summary.completed}</dd></div>
                            <div><dt className="text-vs-muted">Solicitações</dt><dd className="text-2xl font-semibold tabular-nums text-vs-text">{summary.requests}</dd></div>
                            <div><dt className="text-vs-muted">Cancelados ou não realizados</dt><dd className="text-2xl font-semibold tabular-nums text-vs-text">{summary.cancelled}</dd></div>
                            <div>
                                <dt className="text-vs-muted">Frequência</dt>
                                <dd className="text-lg font-semibold text-vs-text">{summary.frequency_days === null ? '—' : `a cada ~${summary.frequency_days} dias`}</dd>
                            </div>
                        </dl>
                        <p className="text-sm text-vs-text">
                            <span className="text-vs-muted">Último serviço: </span>
                            {summary.last_service ? `${summary.last_service.date} · ${summary.last_service.service ?? 'Serviço'}` : 'nenhum serviço concluído ainda'}
                        </p>
                        {financial && (
                            <div className="rounded-md bg-vs-surface/60 p-3 text-sm">
                                <p className="text-vs-muted">Valor registrado em serviços concluídos</p>
                                <p className="text-xl font-semibold tabular-nums text-vs-text">{summary.revenue ?? 'Não informado'}</p>
                                <p className="text-xs text-vs-muted">
                                    {summary.revenue_unknown > 0 ? `${summary.revenue_unknown} ${summary.revenue_unknown === 1 ? 'serviço sem valor informado' : 'serviços sem valor informado'}, fora da soma. ` : ''}
                                    Valor declarado pela empresa; não é comprovante de pagamento. Cancelados não entram.
                                </p>
                            </div>
                        )}
                    </Card>
                </div>

                <section aria-labelledby="proximos" className="grid gap-3">
                    <h2 id="proximos" className="text-lg font-semibold text-vs-text">Próximos agendamentos</h2>
                    {upcoming.length === 0 ? (
                        <p className="text-sm text-vs-muted">Nenhum agendamento futuro para este cliente.</p>
                    ) : (
                        <ul className="grid gap-2 sm:grid-cols-2">
                            {upcoming.map((item) => (
                                <li key={item.id}>
                                    <Link href={requestShow.url({ serviceRequest: item.request_id })} className="block rounded-lg border border-vs-border/70 bg-card p-3 text-sm hover:border-vs-blue">
                                        <span className="font-semibold tabular-nums text-vs-text">{item.starts_at}</span>
                                        <span className="block text-vs-muted">{item.service ?? 'Serviço'} · {item.professional ?? 'Profissional a definir'}</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section aria-labelledby="historico" className="grid gap-3">
                    <h2 id="historico" className="text-lg font-semibold text-vs-text">Histórico</h2>
                    {history.data.length === 0 ? (
                        <EmptyState>Nenhuma solicitação registrada para este cliente.</EmptyState>
                    ) : (
                        <ol className="grid gap-3">
                            {history.data.map((item) => (
                                <li key={item.id} className="rounded-lg border border-vs-border/70 bg-card p-4 text-sm">
                                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                                        <Link href={requestShow.url({ serviceRequest: item.id })} className="font-medium text-vs-link hover:underline">
                                            #{item.id} · {item.service ?? 'Serviço a definir'}
                                        </Link>
                                        <span className="text-xs font-medium text-vs-text">{item.status}</span>
                                    </div>
                                    <p className="text-vs-muted">Aberta em {item.created_at}{item.closed_at ? ` · encerrada em ${item.closed_at}` : ''}</p>
                                    {item.closing_reason && <p className="text-vs-muted">Motivo: {item.closing_reason}</p>}
                                    {item.appointments.length > 0 && (
                                        <ul className="mt-1 grid gap-1">
                                            {item.appointments.map((appointment, index) => (
                                                <li key={index} className="text-vs-text">
                                                    {appointment.starts_at} · {appointment.professional ?? 'Profissional a definir'} · {appointment.status}
                                                    {appointment.cancelling_reason && <span className="block text-vs-muted">Cancelado: {appointment.cancelling_reason}</span>}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                    {item.last_event && <p className="mt-1 text-xs text-vs-muted">Última movimentação: {item.last_event.at}{item.last_event.user ? ` · ${item.last_event.user}` : ''}</p>}
                                </li>
                            ))}
                        </ol>
                    )}
                    <Pagination links={history.links} />
                </section>
            </div>
        </>
    );
}
