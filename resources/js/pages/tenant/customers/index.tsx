import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmAction } from '@/components/vetor-smart/confirm-action';
import { EmptyState } from '@/components/vetor-smart/empty-state';
import { selectClass } from '@/components/vetor-smart/field';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { Pagination } from '@/components/vetor-smart/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { archive, create, edit, index as customersIndex, show } from '@/routes/tenant/customers';
import type { Paginator } from '@/types';

type Row = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    city: string | null;
    status: string;
    completed: number;
    recurring: boolean;
    new: boolean;
    last_service: string | null;
    open: number;
    next_appointment: string | null;
    revenue: string | null;
};

type Filters = { q?: string; status?: string; relacao: string; dias: number };

type Props = {
    filters: Filters;
    periods: number[];
    segments: { key: string; label: string }[];
    indicators: Record<string, number>;
    financial: boolean;
    customers: Paginator<Row>;
};

/** Customer portfolio: search, relationship filters and the figures of each customer, read from its requests. */
export default function CustomersIndex({ filters, periods, segments, indicators, financial, customers }: Props) {
    const [term, setTerm] = useState(filters.q ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const visit = (changes: Partial<Filters>) =>
        router.get(customersIndex.url(), { q: term, status, relacao: filters.relacao, dias: filters.dias, ...changes }, { preserveState: true, replace: true });

    const activeSegment = segments.find((segment) => segment.key === filters.relacao);

    return (
        <>
            <Head title="Clientes" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Clientes"
                    description="Sua carteira: quem já atendeu, quem está em andamento e quem merece um novo contato."
                    action={<Button asChild className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover"><Link href={create.url()}>Novo cliente</Link></Button>}
                />

                <section aria-labelledby="relacionamento" className="grid gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="relacionamento" className="text-base font-semibold text-vs-text">Relacionamento</h2>
                        <div role="group" aria-label="Período" className="inline-flex rounded-md border border-vs-border bg-card p-1">
                            {periods.map((days) => (
                                <button
                                    key={days}
                                    type="button"
                                    aria-pressed={filters.dias === days}
                                    onClick={() => visit({ dias: days })}
                                    className={`h-9 rounded px-3 text-sm font-medium ${filters.dias === days ? 'bg-vs-blue text-white' : 'text-vs-muted hover:text-vs-text'}`}
                                >
                                    {days} dias
                                </button>
                            ))}
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-5">
                        {segments.map((segment) => (
                            <button
                                key={segment.key}
                                type="button"
                                aria-pressed={filters.relacao === segment.key}
                                onClick={() => visit({ relacao: filters.relacao === segment.key ? '' : segment.key })}
                                className={`grid min-h-16 content-start gap-0.5 rounded-xl border p-3 text-left shadow-sm transition-colors ${filters.relacao === segment.key ? 'border-vs-blue bg-vs-blue/10' : 'border-vs-border/70 bg-card hover:border-vs-blue'}`}
                            >
                                <span className="text-2xl font-semibold tabular-nums text-vs-text">{indicators[segment.key] ?? 0}</span>
                                <span className="text-xs text-vs-muted">{segment.label}</span>
                            </button>
                        ))}
                    </div>
                    <p className="text-xs text-vs-muted">
                        Recorrente: 2 ou mais serviços concluídos. Novo: primeiro contato nos últimos {filters.dias} dias. Sem atendimento recente: já teve serviço concluído, mas nenhum nos últimos {filters.dias} dias (não significa que deixou de ser cliente).
                    </p>
                </section>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        visit({});
                    }}
                    className="grid gap-3 sm:grid-cols-[1fr_180px_auto]"
                >
                    <Input value={term} onChange={(event) => setTerm(event.target.value)} placeholder="Buscar por nome, e-mail ou telefone" aria-label="Buscar cliente" className="h-10" />
                    <select value={status} onChange={(event) => setStatus(event.target.value)} aria-label="Situação" className={`${selectClass} h-10`}>
                        <option value="">Todas as situações</option>
                        <option value="active">Ativos</option>
                        <option value="inactive">Arquivados</option>
                    </select>
                    <Button type="submit" variant="outline" className="h-10">Buscar</Button>
                </form>

                {activeSegment && (
                    <div className="flex flex-wrap items-center gap-2 text-sm">
                        <span className="text-vs-muted">Mostrando:</span>
                        <button type="button" onClick={() => visit({ relacao: '' })} className="inline-flex min-h-9 items-center gap-1 rounded-full border border-vs-border bg-vs-surface px-3 text-vs-text hover:border-vs-blue">
                            {activeSegment.label}{['novos', 'sem-atendimento'].includes(activeSegment.key) ? ` (${filters.dias} dias)` : ''} <span aria-hidden="true">×</span><span className="sr-only">(remover filtro)</span>
                        </button>
                    </div>
                )}

                {customers.data.length === 0 ? (
                    <EmptyState>{activeSegment || filters.q ? 'Nenhum cliente com estes filtros.' : 'Nenhum cliente ainda. Os clientes entram aqui pelas solicitações do site, do portal e do atendimento IA, ou por “Novo cliente”.'}</EmptyState>
                ) : (
                    <ul className="grid gap-3 lg:grid-cols-2">
                        {customers.data.map((customer) => (
                            <li key={customer.id} className="grid gap-2 rounded-xl border border-vs-border/70 bg-card p-4 shadow-sm">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <Link href={show.url({ customer: customer.id })} className="text-base font-semibold text-vs-text hover:text-vs-link hover:underline">{customer.name}</Link>
                                    <div className="flex flex-wrap gap-1">
                                        {customer.new && <span className="rounded-full bg-vs-blue/10 px-2 py-0.5 text-xs font-medium text-vs-link">Novo</span>}
                                        {customer.recurring && <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Recorrente</span>}
                                        {customer.status !== 'active' && <span className="rounded-full bg-vs-surface px-2 py-0.5 text-xs font-medium text-vs-muted">Arquivado</span>}
                                    </div>
                                </div>
                                <p className="text-sm text-vs-muted">{[customer.phone, customer.email, customer.city].filter(Boolean).join(' · ') || 'Sem contato cadastrado'}</p>
                                <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:grid-cols-4">
                                    <div><dt className="text-xs text-vs-muted">Serviços</dt><dd className="tabular-nums text-vs-text">{customer.completed}</dd></div>
                                    <div><dt className="text-xs text-vs-muted">Último atendimento</dt><dd className="tabular-nums text-vs-text">{customer.last_service ?? '—'}</dd></div>
                                    <div><dt className="text-xs text-vs-muted">Próximo</dt><dd className="tabular-nums text-vs-text">{customer.next_appointment ?? (customer.open > 0 ? `${customer.open} em aberto` : '—')}</dd></div>
                                    {financial && <div><dt className="text-xs text-vs-muted">Valor registrado</dt><dd className="tabular-nums text-vs-text">{customer.revenue ?? '—'}</dd></div>}
                                </dl>
                                <div className="flex flex-wrap items-center gap-2 pt-1">
                                    <Link href={show.url({ customer: customer.id })} className="inline-flex h-10 items-center rounded-md bg-vs-blue px-4 text-sm font-medium text-white hover:bg-vs-blue-hover">Abrir ficha</Link>
                                    <Link href={edit.url({ customer: customer.id })} className="inline-flex h-10 items-center rounded-md border border-vs-border px-4 text-sm font-medium text-vs-text hover:border-vs-blue">Editar</Link>
                                    {customer.status === 'active' && (
                                        <ConfirmAction
                                            trigger={<button type="button" className="inline-flex h-10 items-center rounded-md px-3 text-sm font-medium text-vs-danger hover:bg-vs-danger-soft">Arquivar</button>}
                                            title="Arquivar cliente?"
                                            description="O cliente deixa de aparecer nas buscas padrão, mas o histórico é mantido."
                                            confirmLabel="Arquivar"
                                            destructive
                                            onConfirm={() => router.delete(archive.url({ customer: customer.id }), { preserveScroll: true })}
                                        />
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                <Pagination links={customers.links} />
            </div>
        </>
    );
}
