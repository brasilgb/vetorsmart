import { Head } from '@inertiajs/react';
import { EmptyState } from '@/components/vetor-smart/empty-state';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type Figures = { requests: number; appointments_done: number; completed: number; conversion: number | null; active_services: number };

type Props = {
    period: string;
    previousPeriod: string;
    current: Figures;
    previous: Figures;
    comparison: { requests: number | null; appointments_done: number | null; completed: number | null; conversion: number | null };
    services: { active: number; on_site: number; site: string };
    seo: { title: boolean; description: boolean; canonical: string | null; indexable: boolean };
    evolution: { month: string; requests: number; appointments_done: number; completed: number }[];
    hasActivity: boolean;
    minRequestsForConversion: number;
    origins: OriginRow[];
    previousOrigins: OriginRow[];
};

type OriginRow = { origin: string; label: string; received: number; scheduled: number; completed: number };

function Delta({ value, unit = '%' }: { value: number | null; unit?: string }) {
    if (value === null) {
        return <span className="text-xs text-vs-muted">sem base de comparação</span>;
    }

    const tone = value > 0 ? 'text-emerald-700 dark:text-emerald-400' : value < 0 ? 'text-vs-danger' : 'text-vs-muted';

    return <span className={`text-xs font-medium ${tone}`}>{value > 0 ? '+' : ''}{value}{unit} vs. mês anterior</span>;
}

function Metric({ label, value, delta, unit }: { label: string; value: string | number; delta: number | null; unit?: string }) {
    return (
        <Card className="border-vs-border/70 p-4 shadow-sm">
            <p className="text-sm text-vs-muted">{label}</p>
            <p className="mt-1 text-2xl font-semibold text-vs-text">{value}</p>
            <Delta value={delta} unit={unit} />
        </Card>
    );
}

/** Real figures of the company: no visits, traffic, revenue or search ranking. */
export default function GrowthIndex({ period, previousPeriod, current, comparison, services, seo, evolution, hasActivity, minRequestsForConversion, origins, previousOrigins }: Props) {
    const portal = origins.find((row) => row.origin === 'portal');
    const portalBefore = previousOrigins.find((row) => row.origin === 'portal');
    const shownOrigins = origins.filter((row) => row.received > 0 || row.origin === 'portal');
    const max = Math.max(1, ...evolution.map((row) => Math.max(row.requests, row.completed, row.appointments_done)));

    return (
        <>
            <Head title="Meu Crescimento" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Meu Crescimento" description={`Números reais da sua empresa em ${period}, comparados com ${previousPeriod}. Visitas e posição em buscadores não são medidas pela VetorSmart.`} />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Metric label="Solicitações recebidas" value={current.requests} delta={comparison.requests} />
                    <Metric label="Agendamentos realizados" value={current.appointments_done} delta={comparison.appointments_done} />
                    <Metric label="Serviços concluídos" value={current.completed} delta={comparison.completed} />
                    <Metric
                        label="Taxa de conversão"
                        value={current.conversion === null ? 'Dados insuficientes' : `${current.conversion}%`}
                        delta={comparison.conversion}
                        unit=" p.p."
                    />
                </div>
                <p className="text-xs text-vs-muted">
                    Conversão = serviços concluídos ÷ solicitações recebidas no mês, exibida a partir de {minRequestsForConversion} solicitações.
                </p>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card className="border-vs-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-vs-text">Serviços</CardTitle>
                            <CardDescription>Ativos no cadastro e exibidos no site publicado.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                <div><dt className="text-vs-muted">Serviços ativos</dt><dd className="text-xl font-semibold text-vs-text">{services.active}</dd></div>
                                <div>
                                    <dt className="text-vs-muted">Exibidos no site</dt>
                                    <dd className="text-xl font-semibold text-vs-text">{services.site === 'published' ? services.on_site : '—'}</dd>
                                    {services.site !== 'published' && <dd className="text-xs text-vs-muted">O site ainda não está publicado.</dd>}
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card className="border-vs-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-vs-text">Seu site nos buscadores</CardTitle>
                            <CardDescription>O que está configurado no site. A VetorSmart não mede se o Google já indexou as páginas.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="grid gap-1 text-sm">
                                <li className={seo.title ? 'text-emerald-700 dark:text-emerald-400' : 'text-vs-muted'}>{seo.title ? '✓' : '—'} Título para buscadores</li>
                                <li className={seo.description ? 'text-emerald-700 dark:text-emerald-400' : 'text-vs-muted'}>{seo.description ? '✓' : '—'} Descrição para buscadores</li>
                                <li className={seo.indexable ? 'text-emerald-700 dark:text-emerald-400' : 'text-vs-muted'}>{seo.indexable ? '✓ Liberado para indexação' : '— Não liberado para indexação (site não publicado ou opção desligada)'}</li>
                                {seo.canonical && <li className="break-all text-vs-muted">Endereço principal: {seo.canonical}</li>}
                            </ul>
                        </CardContent>
                    </Card>
                </div>

                <section aria-labelledby="origens" className="grid gap-3">
                    <h2 id="origens" className="text-lg font-semibold text-vs-text">Origem das solicitações em {period}</h2>
                    {portal && (
                        <div className="grid gap-3 sm:grid-cols-3">
                            <Card className="border-vs-border/70 p-4 shadow-sm">
                                <p className="text-sm text-vs-muted">Recebidas pelo Portal Regional</p>
                                <p className="mt-1 text-2xl font-semibold text-vs-text">{portal.received}</p>
                                <p className="text-xs text-vs-muted">{previousPeriod}: {portalBefore?.received ?? 0}</p>
                            </Card>
                            <Card className="border-vs-border/70 p-4 shadow-sm">
                                <p className="text-sm text-vs-muted">Do portal que chegaram a ser agendadas</p>
                                <p className="mt-1 text-2xl font-semibold text-vs-text">{portal.scheduled}</p>
                                <p className="text-xs text-vs-muted">{previousPeriod}: {portalBefore?.scheduled ?? 0}</p>
                            </Card>
                            <Card className="border-vs-border/70 p-4 shadow-sm">
                                <p className="text-sm text-vs-muted">Do portal com serviço concluído</p>
                                <p className="mt-1 text-2xl font-semibold text-vs-text">{portal.completed}</p>
                                <p className="text-xs text-vs-muted">{previousPeriod}: {portalBefore?.completed ?? 0}</p>
                            </Card>
                        </div>
                    )}
                    <Card className="overflow-hidden border-vs-border/70 shadow-sm">
                        <div className="overflow-x-auto" tabIndex={0} role="region" aria-labelledby="origens">
                            <table className="w-full min-w-[480px] text-left text-sm">
                                <caption className="sr-only">Solicitações recebidas em {period} por origem, e quantas delas foram agendadas e concluídas</caption>
                                <thead className="bg-vs-surface text-vs-muted">
                                    <tr>
                                        <th scope="col" className="px-4 py-3 font-medium">Origem</th>
                                        <th scope="col" className="px-4 py-3 font-medium">Recebidas</th>
                                        <th scope="col" className="px-4 py-3 font-medium">Agendadas</th>
                                        <th scope="col" className="px-4 py-3 font-medium">Concluídas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {shownOrigins.map((row) => (
                                        <tr key={row.origin} className="border-t border-vs-border/70">
                                            <th scope="row" className="px-4 py-3 font-medium text-vs-text">{row.label}</th>
                                            <td className="px-4 py-3 text-vs-text">{row.received}</td>
                                            <td className="px-4 py-3 text-vs-text">{row.scheduled}</td>
                                            <td className="px-4 py-3 text-vs-text">{row.completed}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                    <p className="text-xs text-vs-muted">
                        Contamos as solicitações recebidas no mês e acompanhamos cada uma: "agendadas" teve ao menos um agendamento; "concluídas" teve o serviço concluído. Cliques e visitas não são contados. A receita por origem não é exibida porque o valor cobrado em cada atendimento não é registrado no VetorSmart.
                    </p>
                </section>

                <section aria-labelledby="evolucao" className="grid gap-3">
                    <h2 id="evolucao" className="text-lg font-semibold text-vs-text">Últimos seis meses</h2>
                    {!hasActivity ? (
                        <EmptyState>Ainda não há solicitações, agendamentos realizados ou serviços concluídos nos últimos seis meses.</EmptyState>
                    ) : (
                        <Card className="border-vs-border/70 shadow-sm">
                            <CardContent className="pt-6">
                                <div className="overflow-x-auto" tabIndex={0} role="region" aria-label="Evolução dos últimos seis meses">
                                    <table className="w-full min-w-[520px] text-left text-sm">
                                        <thead className="text-vs-muted">
                                            <tr>
                                                <th className="py-2 pr-4 font-medium">Mês</th>
                                                <th className="py-2 pr-4 font-medium">Solicitações</th>
                                                <th className="py-2 pr-4 font-medium">Agendamentos realizados</th>
                                                <th className="py-2 font-medium">Concluídos</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {evolution.map((row) => (
                                                <tr key={row.month} className="border-t border-vs-border/70 text-vs-text">
                                                    <td className="py-2 pr-4">{row.month}</td>
                                                    {[row.requests, row.appointments_done, row.completed].map((value, index) => (
                                                        <td key={index} className="py-2 pr-4">
                                                            <div className="flex items-center gap-2">
                                                                <span className="w-8 text-right tabular-nums">{value}</span>
                                                                <span aria-hidden="true" className="h-2 rounded bg-vs-blue/70 dark:bg-sky-400/70" style={{ width: `${(value / max) * 100}px` }} />
                                                            </div>
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </section>
            </div>
        </>
    );
}
