import { Head, Link, router } from '@inertiajs/react';
import { EmptyState } from '@/components/vetor-smart/empty-state';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { Pagination } from '@/components/vetor-smart/pagination';
import { selectClass } from '@/components/vetor-smart/field';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { index as requestsIndex, create, show } from '@/routes/tenant/requests';
import type { Paginator } from '@/types';

type Row = {
    id: number;
    customer: string | null;
    service: string | null;
    priority: string;
    origin: string;
    origin_label: string;
    assignee: string | null;
    status: string;
    status_label: string;
    created_at: string;
};

type Props = {
    filters: { status: string; origem: string; responsavel: string; de: string; ate: string };
    statuses: { value: string; label: string }[];
    origins: { value: string; label: string }[];
    assignees: { id: number; name: string }[];
    requests: Paginator<Row>;
};

/**
 * Origin of a request in the list. Requests from the Regional Portal get a badge, so the team spots them at a glance.
 */
export function OriginLabel({ origin, label }: { origin: string; label: string }) {
    if (origin === 'portal') {
        return <span className="inline-flex items-center rounded-full border border-vs-blue/40 bg-vs-blue/10 px-2 py-0.5 text-xs font-medium text-vs-link">{label}</span>;
    }

    return <span className="text-vs-muted">{label}</span>;
}

export default function RequestsIndex({ filters, statuses, origins, assignees, requests }: Props) {
    const filter = (changes: Partial<Props['filters']>) => router.get(requestsIndex.url(), { ...filters, ...changes }, { preserveState: true, replace: true });

    return (
        <>
            <Head title="Solicitações" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Solicitações"
                    description="Fluxo único: solicitação, triagem, agendamento, execução e conclusão."
                    action={<Button asChild className="bg-vs-blue text-white hover:bg-vs-blue-hover"><Link href={create.url()}>Nova solicitação</Link></Button>}
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[repeat(3,minmax(0,220px))_auto_auto]">
                    <select
                        value={filters.status}
                        aria-label="Situação"
                        className={selectClass}
                        onChange={(event) => filter({ status: event.target.value })}
                    >
                        <option value="">Todas as situações</option>
                        {statuses.map((status) => (
                            <option key={status.value} value={status.value}>{status.label}</option>
                        ))}
                    </select>
                    <select value={filters.origem} aria-label="Origem" className={selectClass} onChange={(event) => filter({ origem: event.target.value })}>
                        <option value="">Todas as origens</option>
                        {origins.map((origin) => (
                            <option key={origin.value} value={origin.value}>{origin.label}</option>
                        ))}
                    </select>
                    <select value={filters.responsavel} aria-label="Responsável" className={selectClass} onChange={(event) => filter({ responsavel: event.target.value })}>
                        <option value="">Todos os responsáveis</option>
                        <option value="meus">Atribuídas a mim</option>
                        <option value="nenhum">Sem responsável</option>
                        {assignees.map((member) => (
                            <option key={member.id} value={member.id}>{member.name}</option>
                        ))}
                    </select>
                    <label className="grid gap-1 text-xs text-vs-muted">
                        Recebidas de
                        <input type="date" value={filters.de} max={filters.ate || undefined} onChange={(event) => filter({ de: event.target.value })} className={selectClass} />
                    </label>
                    <label className="grid gap-1 text-xs text-vs-muted">
                        até
                        <input type="date" value={filters.ate} min={filters.de || undefined} onChange={(event) => filter({ ate: event.target.value })} className={selectClass} />
                    </label>
                </div>

                {requests.data.length === 0 ? (
                    <EmptyState>Nenhuma solicitação encontrada. Use “Nova solicitação” para registrar o primeiro atendimento.</EmptyState>
                ) : (
                    <Card className="overflow-hidden border-vs-border/70 shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[880px] text-left text-sm">
                                <thead className="bg-vs-surface text-vs-muted">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Nº</th>
                                        <th className="px-4 py-3 font-medium">Cliente</th>
                                        <th className="px-4 py-3 font-medium">Serviço</th>
                                        <th className="px-4 py-3 font-medium">Origem</th>
                                        <th className="px-4 py-3 font-medium">Responsável</th>
                                        <th className="px-4 py-3 font-medium">Prioridade</th>
                                        <th className="px-4 py-3 font-medium">Situação</th>
                                        <th className="px-4 py-3 font-medium">Criada em</th>
                                        <th className="px-4 py-3 font-medium"><span className="sr-only">Ações</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {requests.data.map((item) => (
                                        <tr key={item.id} className="border-t border-vs-border/70">
                                            <td className="px-4 py-3 font-medium text-vs-text">#{item.id}</td>
                                            <td className="px-4 py-3 text-vs-text">{item.customer ?? '—'}</td>
                                            <td className="px-4 py-3 text-vs-muted">{item.service ?? 'A definir'}</td>
                                            <td className="px-4 py-3"><OriginLabel origin={item.origin} label={item.origin_label} /></td>
                                            <td className="px-4 py-3 text-vs-muted">{item.assignee ?? '—'}</td>
                                            <td className="px-4 py-3 text-vs-muted">{item.priority}</td>
                                            <td className="px-4 py-3 text-vs-text">{item.status_label}</td>
                                            <td className="px-4 py-3 text-vs-muted">{item.created_at}</td>
                                            <td className="px-4 py-3 text-right">
                                                <Link href={show.url({ serviceRequest: item.id })} className="font-medium text-vs-link hover:underline">Abrir</Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                )}

                <Pagination links={requests.links} />
            </div>
        </>
    );
}
