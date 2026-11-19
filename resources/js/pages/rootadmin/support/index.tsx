import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Field, selectClass } from '@/components/vetor-smart/field';
import { Pagination } from '@/components/vetor-smart/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index as supportIndex, show as supportShow, store as supportStore } from '@/routes/rootadmin/support';
import { show as tenantShow } from '@/routes/rootadmin/tenants';
import type { Paginator } from '@/types';

type Option = { value: string; label: string };
type Ticket = { id: number; subject: string; tenant: string | null; tenant_id: number | null; status: string; status_label: string; priority: string; priority_label: string; opened_at: string | null; last_activity_at: string | null };
type Signal = { tenant_id: number; tenant: string; detail: string };

type Props = {
    filters: { status: string; priority: string; q: string; tenant_id: string };
    statuses: Option[];
    priorities: Option[];
    tenants: { id: number; name: string }[];
    tickets: Paginator<Ticket>;
    signals: { suspended: Signal[]; domains: Signal[]; charges: Signal[]; assistant: Signal[] };
};

export const priorityTone: Record<string, string> = {
    urgent: 'border-vs-danger text-vs-danger',
    high: 'border-amber-500 text-amber-700 dark:text-amber-400',
    normal: 'border-vs-border text-vs-text',
    low: 'border-vs-border text-vs-muted',
};

const signalGroups: { key: keyof Props['signals']; title: string }[] = [
    { key: 'suspended', title: 'Suspensas ou canceladas' },
    { key: 'domains', title: 'Domínios com verificação falha' },
    { key: 'charges', title: 'Cobranças sem emissão' },
    { key: 'assistant', title: 'Falhas do provedor de IA no mês' },
];

/** Internal operational support of the platform team. */
export default function SupportIndex({ filters, statuses, priorities, tenants, tickets, signals }: Props) {
    const [status, setStatus] = useState(filters.status);
    const [priority, setPriority] = useState(filters.priority);
    const [term, setTerm] = useState(filters.q);
    const form = useForm({ tenant_id: filters.tenant_id, subject: '', description: '', priority: 'normal' });
    const signalCount = signalGroups.reduce((total, group) => total + signals[group.key].length, 0);

    const search = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(supportIndex.url(), { status, priority, q: term, tenant_id: filters.tenant_id }, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Suporte · RootAdmin" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-vs-text">Suporte operacional</h1>
                    <p className="text-sm text-vs-muted">
                        Empresas com sinais de problema nos dados reais e chamados internos da equipe da plataforma, com histórico de acompanhamento.
                    </p>
                </div>

                <Card className="border-vs-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-vs-text">Sinais operacionais</CardTitle>
                        <CardDescription>Situações encontradas nos dados, sem as empresas de homologação.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {signalCount === 0 ? (
                            <p className="text-sm text-vs-muted">Nenhum sinal de problema no momento.</p>
                        ) : (
                            <div className="grid gap-4 md:grid-cols-2">
                                {signalGroups.filter((group) => signals[group.key].length > 0).map((group) => (
                                    <div key={group.key} className="grid gap-1 text-sm">
                                        <h3 className="font-medium text-vs-text">{group.title}</h3>
                                        <ul className="grid gap-1">
                                            {signals[group.key].map((signal, index) => (
                                                <li key={`${group.key}-${index}`} className="flex flex-wrap justify-between gap-2 text-vs-text">
                                                    <span>
                                                        <Link href={tenantShow.url({ tenant: signal.tenant_id })} className="text-vs-link hover:underline">{signal.tenant}</Link>
                                                        <span className="text-vs-muted"> · {signal.detail}</span>
                                                    </span>
                                                    <button type="button" className="text-vs-link hover:underline" onClick={() => form.setData({ ...form.data, tenant_id: String(signal.tenant_id), subject: `${group.title}: ${signal.tenant}`.slice(0, 160) })}>
                                                        Abrir chamado
                                                    </button>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card className="border-vs-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-vs-text">Novo chamado</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={(event) => { event.preventDefault(); form.post(supportStore.url()); }} className="grid gap-4 md:grid-cols-2">
                            <Field id="tenant_id" label="Empresa (opcional)" error={form.errors.tenant_id}>
                                <select id="tenant_id" value={form.data.tenant_id} onChange={(event) => form.setData('tenant_id', event.target.value)} className={selectClass}>
                                    <option value="">Plataforma (sem empresa)</option>
                                    {tenants.map((tenant) => <option key={tenant.id} value={tenant.id}>{tenant.name}</option>)}
                                </select>
                            </Field>
                            <Field id="priority" label="Prioridade" error={form.errors.priority}>
                                <select id="priority" value={form.data.priority} onChange={(event) => form.setData('priority', event.target.value)} className={selectClass}>
                                    {priorities.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                </select>
                            </Field>
                            <div className="md:col-span-2">
                                <Field id="subject" label="Assunto" error={form.errors.subject}>
                                    <Input id="subject" value={form.data.subject} onChange={(event) => form.setData('subject', event.target.value)} maxLength={160} className="h-10" />
                                </Field>
                            </div>
                            <div className="md:col-span-2">
                                <Field id="description" label="Descrição" error={form.errors.description}>
                                    <textarea id="description" rows={4} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} maxLength={5000} className="w-full rounded-md border border-input bg-card px-3 py-2 text-sm text-vs-text" />
                                </Field>
                            </div>
                            <div><Button type="submit" disabled={form.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Abrir chamado</Button></div>
                        </form>
                    </CardContent>
                </Card>

                <section aria-labelledby="chamados" className="grid gap-3">
                    <h2 id="chamados" className="text-lg font-semibold text-vs-text">Chamados</h2>
                    <form onSubmit={search} className="grid gap-3 md:grid-cols-[1fr_200px_180px_auto]">
                        <Input value={term} onChange={(event) => setTerm(event.target.value)} placeholder="Buscar por assunto" aria-label="Buscar chamado" className="h-10" />
                        <select value={status} onChange={(event) => setStatus(event.target.value)} aria-label="Situação" className="h-10 rounded-md border border-input bg-card px-3 text-sm text-vs-text">
                            <option value="">Em aberto</option>
                            <option value="todos">Todas as situações</option>
                            {statuses.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                        </select>
                        <select value={priority} onChange={(event) => setPriority(event.target.value)} aria-label="Prioridade" className="h-10 rounded-md border border-input bg-card px-3 text-sm text-vs-text">
                            <option value="">Todas as prioridades</option>
                            {priorities.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                        </select>
                        <Button type="submit" className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Filtrar</Button>
                    </form>

                    {tickets.data.length === 0 ? (
                        <p className="text-sm text-vs-muted">Nenhum chamado encontrado.</p>
                    ) : (
                        <ul className="grid gap-2">
                            {tickets.data.map((ticket) => (
                                <li key={ticket.id}>
                                    <Card className="border-vs-border/70 p-4 shadow-sm">
                                        <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                            <div className="grid gap-1 text-sm">
                                                <Link href={supportShow.url({ ticket: ticket.id })} className="font-medium text-vs-link hover:underline">#{ticket.id} · {ticket.subject}</Link>
                                                <p className="text-vs-muted">{ticket.tenant ?? 'Plataforma'} · aberto em {ticket.opened_at} · última atualização {ticket.last_activity_at}</p>
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                <Badge variant="outline" className={priorityTone[ticket.priority] ?? ''}>{ticket.priority_label}</Badge>
                                                <Badge variant="outline" className="border-vs-border text-vs-text">{ticket.status_label}</Badge>
                                            </div>
                                        </div>
                                    </Card>
                                </li>
                            ))}
                        </ul>
                    )}
                    <Pagination links={tickets.links} />
                </section>
            </div>
        </>
    );
}
