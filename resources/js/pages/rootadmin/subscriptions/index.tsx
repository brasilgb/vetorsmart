import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Pagination } from '@/components/vetor-smart/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { edit as billingSettings } from '@/routes/rootadmin/billing';
import { index as subscriptionsIndex, show as subscriptionShow } from '@/routes/rootadmin/subscriptions';
import type { Paginator } from '@/types';

type Row = {
    id: number;
    tenant: string;
    tenant_id: number;
    is_homolog: boolean;
    administratively_suspended: boolean;
    plan: string;
    status: string;
    status_label: string;
    until: string | null;
    cancel_at_period_end: boolean;
};

type Props = {
    filters: { status: string; q: string };
    statuses: { value: string; label: string; total: number }[];
    subscriptions: Paginator<Row>;
    failures: {
        charges: { id: number; subscription_id: number; tenant: string; attempts: number; error: string | null }[];
        webhooks: { id: number; provider: string; reference: string | null; error: string | null; at: string | null }[];
    };
};

export default function SubscriptionsIndex({ filters, statuses, subscriptions, failures }: Props) {
    const [term, setTerm] = useState(filters.q ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    return (
        <>
            <Head title="Assinaturas · RootAdmin" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold text-vs-text">Assinaturas e Financeiro</h1>
                        <p className="text-sm text-vs-muted">Situação financeira das empresas. A suspensão financeira é separada da suspensão administrativa.</p>
                    </div>
                    <Button asChild variant="outline" className="h-10">
                        <Link href={billingSettings.url()}>Plano e Efí</Link>
                    </Button>
                </div>

                <div className="grid gap-3 sm:grid-cols-3 xl:grid-cols-5">
                    {statuses.map((item) => (
                        <Card key={item.value} className="border-vs-border/70 shadow-sm">
                            <CardHeader>
                                <CardDescription>{item.label}</CardDescription>
                                <CardTitle className="text-2xl text-vs-text">{item.total}</CardTitle>
                            </CardHeader>
                        </Card>
                    ))}
                </div>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get(subscriptionsIndex.url(), { q: term, status }, { preserveState: true, replace: true });
                    }}
                    className="grid gap-3 md:grid-cols-[1fr_240px_auto]"
                >
                    <Input value={term} onChange={(event) => setTerm(event.target.value)} placeholder="Buscar empresa" aria-label="Buscar empresa" className="h-10" />
                    <select value={status} onChange={(event) => setStatus(event.target.value)} aria-label="Situação" className="h-10 rounded-md border border-input bg-card px-3 text-sm text-vs-text">
                        <option value="">Todas as situações</option>
                        {statuses.map((item) => (
                            <option key={item.value} value={item.value}>{item.label}</option>
                        ))}
                    </select>
                    <Button type="submit" className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Filtrar</Button>
                </form>

                <Card className="overflow-hidden border-vs-border/70 shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-left text-sm">
                            <thead className="bg-vs-surface text-vs-muted">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Empresa</th>
                                    <th className="px-4 py-3 font-medium">Plano</th>
                                    <th className="px-4 py-3 font-medium">Situação</th>
                                    <th className="px-4 py-3 font-medium">Até</th>
                                    <th className="px-4 py-3 font-medium"><span className="sr-only">Ações</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                {subscriptions.data.length === 0 && (
                                    <tr><td colSpan={5} className="px-4 py-6 text-center text-vs-muted">Nenhuma assinatura encontrada.</td></tr>
                                )}
                                {subscriptions.data.map((row) => (
                                    <tr key={row.id} className="border-t border-vs-border/70">
                                        <td className="px-4 py-3 font-medium text-vs-text">
                                            {row.tenant}
                                            {row.is_homolog && <Badge variant="outline" className="ml-2 border-amber-500 text-amber-700 dark:text-amber-400">Homologação</Badge>}
                                            {row.administratively_suspended && <Badge variant="secondary" className="ml-2">Suspensa pela administração</Badge>}
                                        </td>
                                        <td className="px-4 py-3 text-vs-muted">{row.plan}</td>
                                        <td className="px-4 py-3">
                                            {row.status_label}
                                            {row.cancel_at_period_end && <span className="ml-1 text-vs-muted">(cancelamento agendado)</span>}
                                        </td>
                                        <td className="px-4 py-3 text-vs-muted">{row.until ?? '—'}</td>
                                        <td className="px-4 py-3 text-right">
                                            <Link href={subscriptionShow.url({ subscription: row.id })} className="font-medium text-vs-link hover:underline">Ver</Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <Pagination links={subscriptions.links} />

                <div className="grid gap-4 xl:grid-cols-2">
                    <Card className="border-vs-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-vs-text">Cobranças com falha de emissão</CardTitle>
                            <CardDescription>A rotina diária tenta emitir de novo. Também é possível sincronizar pelo detalhe da assinatura.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {failures.charges.length === 0 ? (
                                <p className="text-sm text-vs-muted">Nenhuma falha pendente.</p>
                            ) : (
                                <ul className="grid gap-2 text-sm">
                                    {failures.charges.map((item) => (
                                        <li key={item.id} className="border-t border-vs-border/70 pt-2">
                                            <Link href={subscriptionShow.url({ subscription: item.subscription_id })} className="font-medium text-vs-link hover:underline">{item.tenant}</Link>
                                            <span className="text-vs-muted"> · cobrança #{item.id} · {item.attempts} tentativa(s)</span>
                                            {item.error && <p className="break-words text-vs-muted">{item.error}</p>}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="border-vs-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-vs-text">Notificações da Efí com falha</CardTitle>
                            <CardDescription>As cobranças em aberto são conciliadas de novo pela rotina diária.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {failures.webhooks.length === 0 ? (
                                <p className="text-sm text-vs-muted">Nenhuma falha registrada.</p>
                            ) : (
                                <ul className="grid gap-2 text-sm">
                                    {failures.webhooks.map((item) => (
                                        <li key={item.id} className="border-t border-vs-border/70 pt-2">
                                            <span className="font-medium text-vs-text">{item.provider}</span>
                                            <span className="text-vs-muted"> · {item.reference ?? '—'} · {item.at}</span>
                                            {item.error && <p className="break-words text-vs-muted">{item.error}</p>}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
