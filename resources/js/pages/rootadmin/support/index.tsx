import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Field, selectClass } from '@/components/vetor-smart/field';
import { Pagination } from '@/components/vetor-smart/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { exportMethod as supportExport, index as supportIndex, show as supportShow, store as supportStore } from '@/routes/rootadmin/support';
import { show as tenantShow } from '@/routes/rootadmin/tenants';
import type { Paginator } from '@/types';

type Option = { value: string; label: string };

type Ticket = {
    id: number;
    subject: string;
    tenant: string | null;
    tenant_id: number | null;
    category_label: string;
    status: string;
    status_label: string;
    priority: string;
    priority_label: string;
    opened_via: string;
    assignee: string | null;
    due_at: string | null;
    overdue: boolean;
    opened_at: string | null;
    last_activity_at: string | null;
};

type Signal = { tenant_id: number; tenant: string; detail: string };

type Filters = { status: string; priority: string; category: string; tenant_id: string; assigned_to: string; q: string };

type Props = {
    filters: Filters;
    statuses: Option[];
    priorities: Option[];
    categories: Option[];
    tenants: { id: number; name: string }[];
    assignees: { id: number; name: string }[];
    indicators: { open: number; waiting: number; overdue: number; unassigned: number };
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

const filterSelect = 'h-10 rounded-md border border-input bg-card px-3 text-sm text-vs-text';

/** Central de Suporte, platform side: every company's requests, indicators, filters and CSV. */
export default function SupportIndex({ filters, statuses, priorities, categories, tenants, assignees, indicators, tickets, signals }: Props) {
    const [values, setValues] = useState<Filters>(filters);
    const form = useForm({ tenant_id: filters.tenant_id, subject: '', description: '', priority: 'normal', category: 'other' });
    const signalCount = signalGroups.reduce((total, group) => total + signals[group.key].length, 0);
    const set = (key: keyof Filters, value: string) => setValues({ ...values, [key]: value });

    const search = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(supportIndex.url(), values, { preserveState: true, replace: true });
    };

    const cards: [string, number, Partial<Filters>][] = [
        ['Em aberto', indicators.open, { status: '' }],
        ['Aguardando a empresa', indicators.waiting, { status: 'waiting' }],
        ['Atrasados', indicators.overdue, { status: 'atrasados' }],
        ['Sem responsável', indicators.unassigned, { status: '', assigned_to: 'ninguem' }],
    ];

    return (
        <>
            <Head title="Suporte · RootAdmin" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-vs-text">Central de Suporte</h1>
                    <p className="text-sm text-vs-muted">Chamados das empresas e da plataforma, com responsável, prazo e histórico, e empresas com sinais de problema nos dados reais.</p>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map(([label, value, preset]) => (
                        <button
                            key={label}
                            type="button"
                            onClick={() => router.get(supportIndex.url(), { ...preset }, { preserveState: false })}
                            className="rounded-xl border border-vs-border/70 bg-card p-4 text-left shadow-sm transition-colors hover:border-vs-blue focus-visible:ring-2 focus-visible:ring-vs-blue focus-visible:outline-none"
                        >
                            <span className="block text-sm text-vs-muted">{label}</span>
                            <span className={`mt-1 block text-2xl font-semibold ${label === 'Atrasados' && value > 0 ? 'text-vs-danger' : 'text-vs-text'}`}>{value}</span>
                        </button>
                    ))}
                </div>

                <section aria-labelledby="chamados" className="grid gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="chamados" className="text-lg font-semibold text-vs-text">Chamados</h2>
                        <Button asChild variant="outline" size="sm">
                            <a href={supportExport.url({ query: filters })}>Exportar CSV</a>
                        </Button>
                    </div>
                    <form onSubmit={search} className="grid gap-3 md:grid-cols-3 xl:grid-cols-[1fr_170px_170px_160px_190px_170px_auto]">
                        <Input value={values.q} onChange={(event) => set('q', event.target.value)} placeholder="Buscar por assunto" aria-label="Buscar chamado" className="h-10" />
                        <select value={values.tenant_id} onChange={(event) => set('tenant_id', event.target.value)} aria-label="Empresa" className={filterSelect}>
                            <option value="">Todas as empresas</option>
                            {tenants.map((tenant) => <option key={tenant.id} value={tenant.id}>{tenant.name}</option>)}
                        </select>
                        <select value={values.status} onChange={(event) => set('status', event.target.value)} aria-label="Situação" className={filterSelect}>
                            <option value="">Em aberto</option>
                            <option value="atrasados">Atrasados</option>
                            <option value="todos">Todas as situações</option>
                            {statuses.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                        </select>
                        <select value={values.priority} onChange={(event) => set('priority', event.target.value)} aria-label="Prioridade" className={filterSelect}>
                            <option value="">Todas as prioridades</option>
                            {priorities.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                        </select>
                        <select value={values.category} onChange={(event) => set('category', event.target.value)} aria-label="Categoria" className={filterSelect}>
                            <option value="">Todas as categorias</option>
                            {categories.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                        </select>
                        <select value={values.assigned_to} onChange={(event) => set('assigned_to', event.target.value)} aria-label="Responsável" className={filterSelect}>
                            <option value="">Qualquer responsável</option>
                            <option value="ninguem">Sem responsável</option>
                            {assignees.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
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
                                                <p className="text-vs-muted">
                                                    {ticket.tenant ?? 'Plataforma'} · {ticket.category_label} · {ticket.opened_via === 'tenant' ? 'aberto pela empresa' : 'aberto pela equipe'} · {ticket.assignee ?? 'sem responsável'}
                                                    {ticket.due_at && ` · prazo ${ticket.due_at}`}
                                                </p>
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                {ticket.overdue && <Badge variant="outline" className="border-vs-danger text-vs-danger">Atrasado</Badge>}
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
                        <CardTitle className="text-vs-text">Novo chamado da equipe</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={(event) => { event.preventDefault(); form.post(supportStore.url()); }} className="grid gap-4 md:grid-cols-3">
                            <Field id="tenant_id" label="Empresa (opcional)" error={form.errors.tenant_id}>
                                <select id="tenant_id" value={form.data.tenant_id} onChange={(event) => form.setData('tenant_id', event.target.value)} className={selectClass}>
                                    <option value="">Plataforma (sem empresa)</option>
                                    {tenants.map((tenant) => <option key={tenant.id} value={tenant.id}>{tenant.name}</option>)}
                                </select>
                            </Field>
                            <Field id="category" label="Categoria" error={form.errors.category}>
                                <select id="category" value={form.data.category} onChange={(event) => form.setData('category', event.target.value)} className={selectClass}>
                                    {categories.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                </select>
                            </Field>
                            <Field id="priority" label="Prioridade" error={form.errors.priority}>
                                <select id="priority" value={form.data.priority} onChange={(event) => form.setData('priority', event.target.value)} className={selectClass}>
                                    {priorities.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                </select>
                            </Field>
                            <div className="md:col-span-3">
                                <Field id="subject" label="Assunto" error={form.errors.subject}>
                                    <Input id="subject" value={form.data.subject} onChange={(event) => form.setData('subject', event.target.value)} maxLength={160} className="h-10" />
                                </Field>
                            </div>
                            <div className="md:col-span-3">
                                <Field id="description" label="Descrição" error={form.errors.description}>
                                    <textarea id="description" rows={4} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} maxLength={5000} className="w-full rounded-md border border-input bg-card px-3 py-2 text-sm text-vs-text" />
                                </Field>
                            </div>
                            <div><Button type="submit" disabled={form.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Abrir chamado</Button></div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
