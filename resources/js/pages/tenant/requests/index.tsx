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
    status: string;
    status_label: string;
    created_at: string;
};

type Props = {
    filters: { status: string };
    statuses: { value: string; label: string }[];
    requests: Paginator<Row>;
};

export default function RequestsIndex({ filters, statuses, requests }: Props) {
    return (
        <>
            <Head title="Solicitações" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Solicitações"
                    description="Fluxo único: solicitação, triagem, agendamento, execução e conclusão."
                    action={<Button asChild className="bg-vs-blue text-white hover:bg-vs-blue-hover"><Link href={create.url()}>Nova solicitação</Link></Button>}
                />

                <div className="grid gap-3 md:grid-cols-[260px_auto]">
                    <select
                        value={filters.status}
                        aria-label="Situação"
                        className={selectClass}
                        onChange={(event) => router.get(requestsIndex.url(), { status: event.target.value }, { preserveState: true, replace: true })}
                    >
                        <option value="">Todas as situações</option>
                        {statuses.map((status) => (
                            <option key={status.value} value={status.value}>{status.label}</option>
                        ))}
                    </select>
                </div>

                {requests.data.length === 0 ? (
                    <EmptyState>Nenhuma solicitação encontrada. Use “Nova solicitação” para registrar o primeiro atendimento.</EmptyState>
                ) : (
                    <Card className="overflow-hidden border-vs-border/70 shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[640px] text-left text-sm">
                                <thead className="bg-vs-surface text-vs-muted">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Nº</th>
                                        <th className="px-4 py-3 font-medium">Cliente</th>
                                        <th className="px-4 py-3 font-medium">Serviço</th>
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
                                            <td className="px-4 py-3 text-vs-muted">{item.priority}</td>
                                            <td className="px-4 py-3 text-vs-text">{item.status_label}</td>
                                            <td className="px-4 py-3 text-vs-muted">{item.created_at}</td>
                                            <td className="px-4 py-3 text-right">
                                                <Link href={show.url({ serviceRequest: item.id })} className="font-medium text-vs-blue hover:underline">Abrir</Link>
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
