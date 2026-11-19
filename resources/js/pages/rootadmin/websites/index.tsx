import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Pagination } from '@/components/vetor-smart/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { show as tenantShow } from '@/routes/rootadmin/tenants';
import { index as websitesIndex } from '@/routes/rootadmin/websites';
import type { Paginator } from '@/types';

type Domain = { id: number; hostname: string; kind: string; status: string; is_primary: boolean; verified_at: string | null };

type Website = {
    id: number;
    tenant_id: number;
    tenant: string;
    is_homolog: boolean;
    tenant_suspended: boolean;
    name: string;
    status: string;
    published_at: string | null;
    domains: Domain[];
};

type Props = {
    filters: { status: string; q: string };
    counts: { published: number; draft: number; unpublished: number; custom_domains_verified: number; custom_domains_pending: number; without_site: number };
    websites: Paginator<Website>;
};

const statusLabel: Record<string, { label: string; tone: string }> = {
    published: { label: 'Publicado', tone: 'border-emerald-600 text-emerald-700 dark:text-emerald-400' },
    draft: { label: 'Rascunho', tone: 'border-vs-border text-vs-muted' },
    unpublished: { label: 'Retirado do ar', tone: 'border-amber-500 text-amber-700 dark:text-amber-400' },
};

/** Sites and addresses of every company. Read-only: publishing and domains stay with each company. */
export default function WebsitesIndex({ filters, counts, websites }: Props) {
    const [term, setTerm] = useState(filters.q);
    const [status, setStatus] = useState(filters.status);

    const search = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(websitesIndex.url(), { q: term, status }, { preserveState: true, replace: true });
    };

    const summary: [string, number][] = [
        ['Publicados', counts.published],
        ['Rascunhos', counts.draft],
        ['Retirados do ar', counts.unpublished],
        ['Domínios próprios verificados', counts.custom_domains_verified],
        ['Domínios próprios pendentes', counts.custom_domains_pending],
        ['Empresas sem site', counts.without_site],
    ];

    return (
        <>
            <Head title="Sites e Domínios · RootAdmin" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-vs-text">Sites e Domínios</h1>
                    <p className="text-sm text-vs-muted">Site de cada empresa e os endereços dele. Somente leitura: a publicação e os domínios são geridos por cada empresa.</p>
                </div>

                <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    {summary.map(([label, value]) => (
                        <Card key={label} className="border-vs-border/70 p-4 shadow-sm">
                            <dt className="text-sm text-vs-muted">{label}</dt>
                            <dd className="mt-1 text-2xl font-semibold text-vs-text">{value}</dd>
                        </Card>
                    ))}
                </dl>

                <form onSubmit={search} className="grid gap-3 md:grid-cols-[1fr_200px_auto]">
                    <Input value={term} onChange={(event) => setTerm(event.target.value)} placeholder="Buscar por empresa" aria-label="Buscar empresa" className="h-10" />
                    <select value={status} onChange={(event) => setStatus(event.target.value)} aria-label="Situação do site" className="h-10 rounded-md border border-input bg-card px-3 text-sm text-vs-text">
                        <option value="">Todas as situações</option>
                        <option value="published">Publicado</option>
                        <option value="draft">Rascunho</option>
                        <option value="unpublished">Retirado do ar</option>
                    </select>
                    <Button type="submit" className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Filtrar</Button>
                </form>

                {websites.data.length === 0 ? (
                    <p className="text-sm text-vs-muted">Nenhum site encontrado.</p>
                ) : (
                    <ul className="grid gap-3">
                        {websites.data.map((website) => (
                            <li key={website.id}>
                                <Card className="border-vs-border/70 p-4 shadow-sm">
                                    <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                        <div className="grid gap-1 text-sm">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Link href={tenantShow.url({ tenant: website.tenant_id })} className="font-medium text-vs-link hover:underline">{website.tenant}</Link>
                                                <Badge variant="outline" className={statusLabel[website.status]?.tone ?? ''}>{statusLabel[website.status]?.label ?? website.status}</Badge>
                                                {website.is_homolog && <Badge variant="outline" className="border-vs-border text-vs-muted">Homologação</Badge>}
                                                {website.tenant_suspended && <Badge variant="outline" className="border-vs-danger text-vs-danger">Empresa suspensa</Badge>}
                                            </div>
                                            <p className="text-vs-muted">{website.name}{website.published_at && ` · publicado em ${website.published_at}`}</p>
                                        </div>
                                        <ul className="grid gap-1 text-sm md:text-right">
                                            {website.domains.length === 0 && <li className="text-vs-muted">Sem endereço próprio</li>}
                                            {website.domains.map((domain) => (
                                                <li key={domain.id} className="break-all text-vs-text">
                                                    {domain.hostname}
                                                    <span className="text-vs-muted">
                                                        {' · '}{domain.kind === 'subdomain' ? 'subdomínio' : 'domínio próprio'}
                                                        {' · '}{domain.status === 'verified' ? `verificado${domain.verified_at ? ` em ${domain.verified_at}` : ''}` : domain.status === 'failed' ? 'falhou' : 'pendente'}
                                                        {domain.is_primary && ' · principal'}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                </Card>
                            </li>
                        ))}
                    </ul>
                )}

                <Pagination links={websites.links} />
            </div>
        </>
    );
}
