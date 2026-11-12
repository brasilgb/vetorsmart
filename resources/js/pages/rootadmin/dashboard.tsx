import { Head, Link } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { show as tenantShow } from '@/routes/rootadmin/tenants';

type Props = {
    totals: { tenants: number; active: number; suspended: number; users: number };
    sites: { published: number };
    appointments: { upcoming: number };
    ai: { period: string; messages: number; provider_calls: number; provider_failures: number; tokens: number };
    pending: { label: string; reason: string }[];
    distribution: { name: string; total: number }[];
    recent: { id: number; name: string; segment: string | null; status: string; created_at: string | null }[];
};

export default function RootadminDashboard({ totals, sites, appointments, ai, pending, distribution, recent }: Props) {
    const maxDistribution = Math.max(1, ...distribution.map((item) => item.total));

    return (
        <>
            <Head title="Visão Geral · RootAdmin" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-vs-text">Visão Geral</h1>
                    <p className="text-sm text-vs-muted">Indicadores reais da plataforma. Não exibe dados operacionais das empresas.</p>
                </div>

                <section aria-labelledby="indicadores-empresas" className="grid gap-4">
                    <h2 id="indicadores-empresas" className="text-sm font-medium text-vs-muted">Empresas e uso</h2>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <Metric label="Empresas cadastradas" value={totals.tenants} />
                        <Metric label="Empresas ativas" value={totals.active} />
                        <Metric label="Empresas suspensas" value={totals.suspended} />
                        <Metric label="Usuários cadastrados" value={totals.users} />
                        <Metric label="Sites publicados" value={sites.published} />
                        <Metric label="Agendamentos futuros" value={appointments.upcoming} />
                    </div>
                </section>

                <section aria-labelledby="indicadores-ia" className="grid gap-4">
                    <h2 id="indicadores-ia" className="text-sm font-medium text-vs-muted">Atendimento IA · {ai.period}</h2>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <Metric label="Mensagens processadas" value={ai.messages} />
                        <Metric label="Chamadas ao provedor" value={ai.provider_calls} />
                        <Metric label="Falhas do provedor" value={ai.provider_failures} />
                        <Metric label="Tokens consumidos" value={ai.tokens} />
                    </div>
                    <p className="text-sm text-vs-muted">Custo estimado indisponível: ainda não há tabela de preços do provedor cadastrada.</p>
                </section>

                <div className="grid gap-4 xl:grid-cols-2">
                    <Card className="border-vs-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-vs-text">Empresas por segmento</CardTitle>
                        </CardHeader>
                        <div className="grid gap-3 px-6 pb-6">
                            {distribution.length === 0 && <p className="text-sm text-vs-muted">Nenhuma empresa cadastrada.</p>}
                            {distribution.map((item) => (
                                <div key={item.name} className="grid gap-1">
                                    <div className="flex justify-between text-sm text-vs-text">
                                        <span>{item.name}</span>
                                        <span className="font-medium">{item.total}</span>
                                    </div>
                                    <div className="h-2 rounded-full bg-vs-surface">
                                        <div
                                            className="h-2 rounded-full bg-vs-blue"
                                            style={{ width: `${(item.total / maxDistribution) * 100}%` }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Card>

                    <Card className="border-vs-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-vs-text">Cadastradas recentemente</CardTitle>
                        </CardHeader>
                        <div className="overflow-x-auto px-6 pb-6">
                            <table className="w-full text-left text-sm">
                                <thead className="text-vs-muted">
                                    <tr>
                                        <th className="py-2 font-medium">Empresa</th>
                                        <th className="py-2 font-medium">Segmento</th>
                                        <th className="py-2 font-medium">Status</th>
                                        <th className="py-2 font-medium">Cadastro</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recent.map((tenant) => (
                                        <tr key={tenant.id} className="border-t border-vs-border/70">
                                            <td className="py-2">
                                                <Link href={tenantShow.url({ tenant: tenant.id })} className="font-medium text-vs-blue hover:underline">
                                                    {tenant.name}
                                                </Link>
                                            </td>
                                            <td className="py-2 text-vs-muted">{tenant.segment ?? '—'}</td>
                                            <td className="py-2">
                                                <Badge variant={tenant.status === 'active' ? 'default' : 'secondary'}>
                                                    {tenant.status === 'active' ? 'Ativa' : tenant.status === 'suspended' ? 'Suspensa' : 'Cancelada'}
                                                </Badge>
                                            </td>
                                            <td className="py-2 text-vs-muted">{tenant.created_at ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </div>

                <Card className="border-vs-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-vs-text">Indisponíveis no momento</CardTitle>
                        <CardDescription>Estes indicadores dependem de módulos ainda não implementados. Nenhum valor é estimado.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="grid gap-2 text-sm">
                            {pending.map((item) => (
                                <li key={item.label} className="flex flex-wrap items-baseline justify-between gap-2 border-t border-vs-border/70 pt-2">
                                    <span className="font-medium text-vs-text">{item.label}</span>
                                    <span className="text-vs-muted">{item.reason}</span>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <Card className="border-vs-border/70 shadow-sm">
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-3xl text-vs-text">{value}</CardTitle>
            </CardHeader>
        </Card>
    );
}
