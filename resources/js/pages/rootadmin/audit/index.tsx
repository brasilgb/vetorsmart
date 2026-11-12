import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Pagination } from '@/components/vetor-smart/pagination';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index as auditIndex } from '@/routes/rootadmin/audit';
import type { Paginator } from '@/types';

type Entry = {
    id: number;
    created_at: string | null;
    action: string;
    tenant: string | null;
    user: string | null;
    subject: string | null;
    metadata: Record<string, unknown> | null;
};

type Props = {
    filters: { action?: string; tenant_id?: string; from?: string; to?: string };
    entries: Paginator<Entry>;
};

/** Metadata is sanitized when written (no passwords, tokens or secrets), so it is safe to show. */
export default function AuditIndex({ filters, entries }: Props) {
    const [action, setAction] = useState(filters.action ?? '');
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    const search = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(auditIndex.url(), { action, from, to, tenant_id: filters.tenant_id ?? '' }, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Auditoria" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-vs-text">Auditoria</h1>
                    <p className="text-sm text-vs-muted">Registros de operações administrativas e sensíveis, do mais recente para o mais antigo.</p>
                </div>

                <form onSubmit={search} className="grid gap-3 md:grid-cols-[1fr_180px_180px_auto]">
                    <Input value={action} onChange={(event) => setAction(event.target.value)} placeholder="Ação (ex.: tenant.suspended)" aria-label="Ação" className="h-10" />
                    <Input type="date" value={from} onChange={(event) => setFrom(event.target.value)} aria-label="De" className="h-10" />
                    <Input type="date" value={to} onChange={(event) => setTo(event.target.value)} aria-label="Até" className="h-10" />
                    <Button type="submit" className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Filtrar</Button>
                </form>

                <Card className="overflow-hidden border-vs-border/70 shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[800px] text-left text-sm">
                            <thead className="bg-vs-surface text-vs-muted">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Data</th>
                                    <th className="px-4 py-3 font-medium">Ação</th>
                                    <th className="px-4 py-3 font-medium">Empresa</th>
                                    <th className="px-4 py-3 font-medium">Responsável</th>
                                    <th className="px-4 py-3 font-medium">Entidade</th>
                                    <th className="px-4 py-3 font-medium">Detalhes</th>
                                </tr>
                            </thead>
                            <tbody>
                                {entries.data.length === 0 && (
                                    <tr><td colSpan={6} className="px-4 py-6 text-center text-vs-muted">Nenhum registro encontrado.</td></tr>
                                )}
                                {entries.data.map((entry) => (
                                    <tr key={entry.id} className="border-t border-vs-border/70 align-top">
                                        <td className="px-4 py-3 whitespace-nowrap text-vs-muted">{entry.created_at}</td>
                                        <td className="px-4 py-3 font-mono text-xs text-vs-text">{entry.action}</td>
                                        <td className="px-4 py-3 text-vs-text">{entry.tenant ?? '—'}</td>
                                        <td className="px-4 py-3 text-vs-text">{entry.user ?? '—'}</td>
                                        <td className="px-4 py-3 text-vs-muted">{entry.subject ?? '—'}</td>
                                        <td className="px-4 py-3">
                                            {entry.metadata && Object.keys(entry.metadata).length > 0 ? (
                                                <pre tabIndex={0} aria-label="Metadados da ação" className="max-w-[320px] overflow-x-auto rounded bg-vs-surface p-2 text-xs text-vs-muted">
                                                    {JSON.stringify(entry.metadata, null, 2)}
                                                </pre>
                                            ) : (
                                                <span className="text-vs-muted">—</span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <Pagination links={entries.links} />
            </div>
        </>
    );
}
