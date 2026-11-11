import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Circle } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { index as requestsIndex } from '@/routes/tenant/requests';
import { index as agendaIndex } from '@/routes/tenant/agenda';

type Metrics = {
    customers: number;
    services: number;
    professionals: number;
};

type Operations = {
    open_requests: number;
    today_appointments: number;
    in_progress: number;
    completed_30_days: number;
    cancelled_30_days: number;
    conversion_30_days: number | null;
    upcoming: { id: number; starts_at: string; customer: string | null; service: string | null; professional: string | null }[];
};

type ChecklistItem = {
    key: string;
    label: string;
    done: boolean;
};

type Props = {
    metrics: Metrics;
    operations: Operations;
    checklist: ChecklistItem[];
    needsSetup: boolean;
};

export default function Dashboard({ metrics, operations, checklist, needsSetup }: Props) {
    const indicators = [
        { label: 'Clientes ativos', value: metrics.customers },
        { label: 'Serviços ativos', value: metrics.services },
        { label: 'Profissionais ativos', value: metrics.professionals },
    ];

    const operational = [
        { label: 'Solicitações abertas', value: operations.open_requests, href: requestsIndex.url() },
        { label: 'Agendamentos de hoje', value: operations.today_appointments, href: agendaIndex.url({ query: { view: 'day' } }) },
        { label: 'Em andamento', value: operations.in_progress, href: requestsIndex.url({ query: { status: 'in_progress' } }) },
        { label: 'Concluídos (30 dias)', value: operations.completed_30_days, href: requestsIndex.url({ query: { status: 'completed' } }) },
        { label: 'Cancelados (30 dias)', value: operations.cancelled_30_days, href: requestsIndex.url({ query: { status: 'cancelled' } }) },
        { label: 'Conversão (30 dias)', value: operations.conversion_30_days === null ? '—' : `${operations.conversion_30_days}%`, href: null },
    ];

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-vs-text">Painel</h1>
                    <p className="text-sm text-vs-muted">Indicadores reais da sua empresa.</p>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    {indicators.map((item) => (
                        <Card key={item.label} className="border-vs-border/70 shadow-sm">
                            <CardHeader>
                                <CardDescription>{item.label}</CardDescription>
                                <CardTitle className="text-3xl text-vs-text">{item.value}</CardTitle>
                            </CardHeader>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {operational.map((item) => (
                        <Card key={item.label} className="border-vs-border/70 shadow-sm">
                            <CardHeader>
                                <CardDescription>{item.label}</CardDescription>
                                <CardTitle className="text-2xl text-vs-text">
                                    {item.href ? <Link href={item.href} className="hover:underline">{item.value}</Link> : item.value}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                    ))}
                </div>

                <Card className="border-vs-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-vs-text">Próximos compromissos</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {operations.upcoming.length === 0 ? (
                            <p className="text-sm text-vs-muted">Nenhum atendimento agendado à frente.</p>
                        ) : (
                            <ul className="grid gap-2 text-sm text-vs-text">
                                {operations.upcoming.map((item) => (
                                    <li key={item.id} className="rounded-md border border-vs-border/70 p-3">
                                        <span className="font-medium">{item.starts_at}</span> · {item.service ?? 'Serviço'} · {item.customer ?? '—'} · {item.professional ?? '—'}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                {needsSetup && (
                    <Card className="border-vs-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-vs-text">Configure sua empresa para começar</CardTitle>
                            <CardDescription>
                                Você pode concluir estes passos depois. Nada bloqueia o acesso aos demais recursos.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="grid gap-3">
                                {checklist.map((item) => (
                                    <li key={item.key} className="flex items-center gap-3 text-sm text-vs-text">
                                        {item.done ? (
                                            <CheckCircle2 className="size-5 text-vs-cyan" aria-label="Concluído" />
                                        ) : (
                                            <Circle className="size-5 text-vs-muted" aria-label="Pendente" />
                                        )}
                                        <span className={item.done ? 'text-vs-muted line-through' : ''}>{item.label}</span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
