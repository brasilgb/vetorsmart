import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Pagination } from '@/components/vetor-smart/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { exportMethod as growthExport, index as growthIndex } from '@/routes/rootadmin/growth';
import { show as tenantShow } from '@/routes/rootadmin/tenants';
import type { Paginator } from '@/types';

type Row = {
    id: number;
    name: string;
    is_homolog: boolean;
    site: 'none' | 'published' | 'draft' | 'unpublished';
    active_services: number;
    public_services: number;
    requests: number;
    appointments: number;
    completed: number;
    conversion: number | null;
    seo: { title: boolean; description: boolean; canonical: string | null; indexable: boolean; in_sitemap: boolean };
};

type Props = {
    filters: { mes: string; q: string; site: string };
    months: { value: string; label: string }[];
    period: string;
    overview: { with_site: number; without_site: number; public_services: number; active_tenants: number; tenants: number };
    evolution: { month: string; tenants: number; sites: number; requests: number; appointments: number; completed: number }[];
    tenants: Paginator<Row>;
    minRequestsForConversion: number;
};

const siteLabel: Record<Row['site'], { label: string; tone: string }> = {
    published: { label: 'Publicado', tone: 'border-emerald-600 text-emerald-700 dark:text-emerald-400' },
    draft: { label: 'Rascunho', tone: 'border-vs-border text-vs-muted' },
    unpublished: { label: 'Retirado', tone: 'border-amber-500 text-amber-700 dark:text-amber-400' },
    none: { label: 'Sem site', tone: 'border-vs-border text-vs-muted' },
};

function Check({ ok, label }: { ok: boolean; label: string }) {
    return <span className={ok ? 'text-emerald-700 dark:text-emerald-400' : 'text-vs-muted'}>{ok ? '✓' : '—'} {label}</span>;
}

/** Real activity and technical SEO per company. Indexable means allowed by the local configuration, never "indexed". */
export default function GrowthIndex({ filters, months, period, overview, evolution, tenants, minRequestsForConversion }: Props) {
    const [term, setTerm] = useState(filters.q);
    const [month, setMonth] = useState(filters.mes);
    const [site, setSite] = useState(filters.site);

    const search = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(growthIndex.url(), { q: term, mes: month, site }, { preserveState: true, replace: true });
    };

    const cards: [string, number][] = [
        ['Empresas com site publicado', overview.with_site],
        ['Empresas sem site publicado', overview.without_site],
        ['Serviços exibidos nos sites', overview.public_services],
        [`Empresas com atividade em ${period}`, overview.active_tenants],
    ];

    return (
        <>
            <Head title="Crescimento e SEO · RootAdmin" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold text-vs-text">Crescimento e SEO</h1>
                    <p className="text-sm text-vs-muted">
                        Atividade real e SEO técnico das empresas, sem as de homologação nos totais. “Indexável” indica que a configuração permite a
                        indexação; não significa que a página já esteja indexada em buscadores.
                    </p>
                </div>
                    <Button asChild variant="outline" size="sm">
                        <a href={growthExport.url({ query: filters })}>Exportar CSV</a>
                    </Button>
                </div>

                <dl className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map(([label, value]) => (
                        <Card key={label} className="border-vs-border/70 p-4 shadow-sm">
                            <dt className="text-sm text-vs-muted">{label}</dt>
                            <dd className="mt-1 text-2xl font-semibold text-vs-text">{value}</dd>
                        </Card>
                    ))}
                </dl>

                <Card className="border-vs-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-vs-text">Evolução mensal</CardTitle>
                        <CardDescription>Últimos seis meses, com dados gravados.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto" tabIndex={0} role="region" aria-label="Evolução mensal">
                            <table className="w-full min-w-[560px] text-left text-sm">
                                <thead className="text-vs-muted">
                                    <tr>
                                        <th className="py-2 pr-4 font-medium">Mês</th>
                                        <th className="py-2 pr-4 text-right font-medium">Empresas novas</th>
                                        <th className="py-2 pr-4 text-right font-medium">Sites publicados</th>
                                        <th className="py-2 pr-4 text-right font-medium">Solicitações</th>
                                        <th className="py-2 pr-4 text-right font-medium">Agendamentos</th>
                                        <th className="py-2 text-right font-medium">Concluídos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {evolution.map((row) => (
                                        <tr key={row.month} className="border-t border-vs-border/70 text-vs-text">
                                            <td className="py-2 pr-4">{row.month}</td>
                                            <td className="py-2 pr-4 text-right">{row.tenants}</td>
                                            <td className="py-2 pr-4 text-right">{row.sites}</td>
                                            <td className="py-2 pr-4 text-right">{row.requests}</td>
                                            <td className="py-2 pr-4 text-right">{row.appointments}</td>
                                            <td className="py-2 text-right">{row.completed}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <form onSubmit={search} className="grid gap-3 md:grid-cols-[1fr_160px_200px_auto]">
                    <Input value={term} onChange={(event) => setTerm(event.target.value)} placeholder="Buscar por empresa" aria-label="Buscar empresa" className="h-10" />
                    <select value={month} onChange={(event) => setMonth(event.target.value)} aria-label="Mês" className="h-10 rounded-md border border-input bg-card px-3 text-sm text-vs-text">
                        {months.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                    </select>
                    <select value={site} onChange={(event) => setSite(event.target.value)} aria-label="Site" className="h-10 rounded-md border border-input bg-card px-3 text-sm text-vs-text">
                        <option value="">Todas as empresas</option>
                        <option value="publicado">Com site publicado</option>
                        <option value="sem-site">Sem site publicado</option>
                    </select>
                    <Button type="submit" className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Filtrar</Button>
                </form>

                <Card className="overflow-hidden border-vs-border/70 shadow-sm">
                    <div className="overflow-x-auto" tabIndex={0} role="region" aria-label="Indicadores por empresa">
                        <table className="w-full min-w-[1040px] text-left text-sm">
                            <thead className="bg-vs-surface text-vs-muted">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Empresa</th>
                                    <th className="px-4 py-3 font-medium">Site</th>
                                    <th className="px-4 py-3 text-right font-medium">Serviços (ativos / no site)</th>
                                    <th className="px-4 py-3 text-right font-medium">Solicitações</th>
                                    <th className="px-4 py-3 text-right font-medium">Agendamentos</th>
                                    <th className="px-4 py-3 text-right font-medium">Concluídos</th>
                                    <th className="px-4 py-3 text-right font-medium">Conversão</th>
                                    <th className="px-4 py-3 font-medium">SEO técnico</th>
                                </tr>
                            </thead>
                            <tbody>
                                {tenants.data.length === 0 && <tr><td colSpan={8} className="px-4 py-6 text-center text-vs-muted">Nenhuma empresa encontrada.</td></tr>}
                                {tenants.data.map((row) => (
                                    <tr key={row.id} className="border-t border-vs-border/70 align-top text-vs-text">
                                        <td className="px-4 py-3">
                                            <Link href={tenantShow.url({ tenant: row.id })} className="text-vs-link hover:underline">{row.name}</Link>
                                            {row.is_homolog && <Badge variant="outline" className="ml-2 border-vs-border text-vs-muted">Homologação</Badge>}
                                        </td>
                                        <td className="px-4 py-3"><Badge variant="outline" className={siteLabel[row.site].tone}>{siteLabel[row.site].label}</Badge></td>
                                        <td className="px-4 py-3 text-right">{row.active_services} / {row.public_services}</td>
                                        <td className="px-4 py-3 text-right">{row.requests}</td>
                                        <td className="px-4 py-3 text-right">{row.appointments}</td>
                                        <td className="px-4 py-3 text-right">{row.completed}</td>
                                        <td className="px-4 py-3 text-right">{row.conversion === null ? <span className="text-vs-muted">Dados insuficientes</span> : `${row.conversion}%`}</td>
                                        <td className="px-4 py-3">
                                            <div className="grid gap-0.5 text-xs">
                                                <Check ok={row.seo.title} label="Título" />
                                                <Check ok={row.seo.description} label="Descrição" />
                                                <Check ok={row.seo.indexable} label="Indexável e no sitemap" />
                                                {row.seo.canonical && <span className="break-all text-vs-muted">{row.seo.canonical}</span>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
                <p className="text-xs text-vs-muted">
                    Conversão = solicitações concluídas no mês ÷ solicitações recebidas no mês, exibida a partir de {minRequestsForConversion} solicitações.
                </p>

                <Pagination links={tenants.links} />
            </div>
        </>
    );
}
