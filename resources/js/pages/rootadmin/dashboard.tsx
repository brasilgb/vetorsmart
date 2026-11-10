import { Head, Link } from '@inertiajs/react';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { show as tenantShow } from '@/routes/rootadmin/tenants';

type Props = {
    totals: { tenants: number; active: number; suspended: number; users: number };
    distribution: { name: string; total: number }[];
    recent: { id: number; name: string; segment: string | null; status: string; created_at: string | null }[];
};

export default function RootadminDashboard({ totals, distribution, recent }: Props) {
    const maxDistribution = Math.max(1, ...distribution.map((item) => item.total));

    return (
        <>
            <Head title="Console RootAdmin" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-vs-text">Console RootAdmin</h1>
                    <p className="text-sm text-vs-muted">Visão global da plataforma. Não exibe dados operacionais das empresas.</p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Metric label="Total de empresas" value={totals.tenants} />
                    <Metric label="Empresas ativas" value={totals.active} />
                    <Metric label="Empresas suspensas" value={totals.suspended} />
                    <Metric label="Total de usuários" value={totals.users} />
                </div>

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
