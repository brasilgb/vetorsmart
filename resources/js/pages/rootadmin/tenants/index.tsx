import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Pagination } from '@/components/vetor-smart/pagination';
import { index as tenantsIndex, show as tenantShow } from '@/routes/rootadmin/tenants';
import type { Paginator } from '@/types';

type TenantRow = {
    id: number;
    name: string;
    slug: string;
    segment: string | null;
    segment_id: number | null;
    owner: { name: string; email: string } | null;
    status: string;
    created_at: string | null;
};

type Props = {
    filters: { q?: string; status?: string; segment_id?: string };
    segments: { id: number; name: string }[];
    tenants: Paginator<TenantRow>;
};

const statusLabel: Record<string, string> = { active: 'Ativa', suspended: 'Suspensa', cancelled: 'Cancelada' };

export default function TenantsIndex({ filters, segments, tenants }: Props) {
    const [term, setTerm] = useState(filters.q ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [segmentId, setSegmentId] = useState(filters.segment_id ?? '');

    const search = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(tenantsIndex.url(), { q: term, status, segment_id: segmentId }, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Empresas" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-vs-text">Empresas</h1>
                    <p className="text-sm text-vs-muted">Cadastros da plataforma. Empresas não são excluídas por esta tela.</p>
                </div>

                <form onSubmit={search} className="grid gap-3 md:grid-cols-[1fr_180px_220px_auto]">
                    <Input value={term} onChange={(event) => setTerm(event.target.value)} placeholder="Buscar por nome" aria-label="Buscar por nome" className="h-10" />
                    <select value={status} onChange={(event) => setStatus(event.target.value)} aria-label="Status" className="h-10 rounded-md border border-vs-border bg-white px-3 text-sm">
                        <option value="">Todos os status</option>
                        <option value="active">Ativas</option>
                        <option value="suspended">Suspensas</option>
                        <option value="cancelled">Canceladas</option>
                    </select>
                    <select value={segmentId} onChange={(event) => setSegmentId(event.target.value)} aria-label="Segmento" className="h-10 rounded-md border border-vs-border bg-white px-3 text-sm">
                        <option value="">Todos os segmentos</option>
                        {segments.map((segment) => (
                            <option key={segment.id} value={segment.id}>{segment.name}</option>
                        ))}
                    </select>
                    <Button type="submit" className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Filtrar</Button>
                </form>

                <Card className="overflow-hidden border-vs-border/70 shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-left text-sm">
                            <thead className="bg-vs-surface text-vs-muted">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Nome</th>
                                    <th className="px-4 py-3 font-medium">Segmento</th>
                                    <th className="px-4 py-3 font-medium">Responsável</th>
                                    <th className="px-4 py-3 font-medium">Status</th>
                                    <th className="px-4 py-3 font-medium">Cadastro</th>
                                    <th className="px-4 py-3 font-medium"><span className="sr-only">Ações</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                {tenants.data.length === 0 && (
                                    <tr><td colSpan={6} className="px-4 py-6 text-center text-vs-muted">Nenhuma empresa encontrada.</td></tr>
                                )}
                                {tenants.data.map((tenant) => (
                                    <tr key={tenant.id} className="border-t border-vs-border/70">
                                        <td className="px-4 py-3 font-medium text-vs-text">{tenant.name}</td>
                                        <td className="px-4 py-3 text-vs-muted">{tenant.segment ?? '—'}</td>
                                        <td className="px-4 py-3 text-vs-muted">{tenant.owner ? `${tenant.owner.name} (${tenant.owner.email})` : '—'}</td>
                                        <td className="px-4 py-3">
                                            <Badge variant={tenant.status === 'active' ? 'default' : 'secondary'}>{statusLabel[tenant.status] ?? tenant.status}</Badge>
                                        </td>
                                        <td className="px-4 py-3 text-vs-muted">{tenant.created_at ?? '—'}</td>
                                        <td className="px-4 py-3 text-right">
                                            <Link href={tenantShow.url({ tenant: tenant.id })} className="font-medium text-vs-blue hover:underline">Ver</Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <Pagination links={tenants.links} />
            </div>
        </>
    );
}
