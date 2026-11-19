import { Head, Link, router } from '@inertiajs/react';
import { selectClass } from '@/components/vetor-smart/field';
import { index as growthIndex } from '@/routes/tenant/growth';
import { EmptyState } from '@/components/vetor-smart/empty-state';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type Figures = {
    requests: number;
    converted: number;
    appointments_done: number;
    completed: number;
    cancelled: number;
    conversion: number | null;
    revenue: string | null;
    revenue_known: number;
    revenue_unknown: number;
    ticket: string | null;
};

type Option = { value: string; label: string };

type Recommendation = { key: string; tone: 'attention' | 'info' | 'warning'; title: string; description: string; count: number; action: string; href: string };

type OriginRow = { origin: string; label: string; received: number; scheduled: number; completed: number; cancelled: number; href: string };

type Props = {
    period: string;
    previousPeriod: string;
    current: Figures;
    previous: Figures;
    comparison: { requests: number | null; converted: number | null; completed: number | null; cancelled: number | null; conversion: number | null; revenue: number | null; ticket: number | null };
    filters: { periodo: string; origem: string; responsavel: string };
    options: { periods: Option[]; origins: Option[]; assignees: { id: number; name: string }[] };
    links: { received: string; converted: string; completed: string; cancelled: string; without_amount: string };
    recommendations: Recommendation[];
    customers: { total: number; new: number; recurring: number };
    closingReasons: { reason: string; status: string; total: number }[];
    revenueByOrigin: { origin: string; label: string; completed: number; revenue: string | null; revenue_known: number; revenue_unknown: number; ticket: string | null }[];
    services: { active: number; on_site: number; site: string };
    seo: { title: boolean; description: boolean; canonical: string | null; indexable: boolean };
    evolution: { month: string; requests: number; appointments_done: number; completed: number; revenue: string | null; revenue_cents: number; revenue_unknown: number }[];
    hasActivity: boolean;
    minRequestsForConversion: number;
    origins: OriginRow[];
    previousOrigins: Omit<OriginRow, 'href'>[];
};

/** Change against the previous period. With "inverse", a rise is bad (cancellations) and shows in red. */
function Delta({ value, unit = '%', inverse = false }: { value: number | null; unit?: string; inverse?: boolean }) {
    if (value === null) {
        return <span className="text-xs text-vs-muted">sem base de comparação</span>;
    }

    const good = inverse ? value < 0 : value > 0;
    const bad = inverse ? value > 0 : value < 0;
    const tone = good ? 'text-emerald-700 dark:text-emerald-400' : bad ? 'text-vs-danger' : 'text-vs-muted';

    return <span className={`text-xs font-medium ${tone}`}>{value > 0 ? '+' : ''}{value}{unit} vs. período anterior</span>;
}

/** A headline figure that opens the records behind it. */
function Metric({ label, value, hint, delta, unit, inverse = false, href }: { label: string; value: string | number; hint?: string; delta: number | null; unit?: string; inverse?: boolean; href?: string }) {
    const body = (
        <>
            <p className="text-sm text-vs-muted">{label}</p>
            <p className="mt-1 text-2xl font-semibold tabular-nums text-vs-text">{value}</p>
            {hint && <p className="text-xs text-vs-muted">{hint}</p>}
            <Delta value={delta} unit={unit} inverse={inverse} />
        </>
    );

    return href ? (
        <Link href={href} className="block rounded-xl border border-vs-border/70 bg-card p-4 shadow-sm transition-colors hover:border-vs-blue focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-vs-blue">
            {body}
            <span className="mt-1 block text-xs text-vs-link">Ver registros →</span>
        </Link>
    ) : (
        <Card className="border-vs-border/70 p-4 shadow-sm">{body}</Card>
    );
}

const toneClass: Record<Recommendation['tone'], string> = {
    attention: 'border-l-amber-500 dark:border-l-amber-400',
    warning: 'border-l-red-600 dark:border-l-red-400',
    info: 'border-l-vs-blue dark:border-l-sky-400',
};

const toneLabel: Record<Recommendation['tone'], string> = { attention: 'Atenção', warning: 'Alerta', info: 'Lembrete' };

/** A horizontal bar for one value of a column; the number beside it is the label. */
function Bar({ value, max, label }: { value: number; max: number; label: string }) {
    return <span aria-hidden="true" title={label} className="h-2 rounded-r bg-vs-blue/70 dark:bg-sky-400/70" style={{ width: `${max > 0 ? Math.max(value > 0 ? 4 : 0, (value / max) * 96) : 0}px` }} />;
}

/** Commercial results of the company: what to do now, operational figures, declared revenue and their records. */
export default function GrowthIndex({ period, previousPeriod, current, comparison, filters, options, links, recommendations, customers, closingReasons, services, seo, evolution, hasActivity, minRequestsForConversion, origins, previousOrigins, revenueByOrigin }: Props) {
    const filter = (changes: Partial<Props['filters']>) => router.get(growthIndex.url(), { ...filters, ...changes }, { preserveState: true, preserveScroll: true, replace: true });
    const shownRevenue = revenueByOrigin.filter((row) => row.completed > 0);
    const shownOrigins = origins.filter((row) => row.received > 0);
    const maxCount = Math.max(1, ...evolution.map((row) => Math.max(row.requests, row.completed)));
    const maxRevenue = Math.max(0, ...evolution.map((row) => row.revenue_cents));

    return (
        <>
            <Head title="Meu Crescimento" />

            <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
                <PageHeader title="Meu Crescimento" description={`Resultados reais da sua empresa em ${period}, comparados com ${previousPeriod}. Visitas e posição em buscadores não são medidas pela VetorSmart.`} />

                <div className="grid gap-3 sm:grid-cols-3 lg:max-w-3xl" role="group" aria-label="Filtros dos indicadores">
                    <label className="grid gap-1 text-xs text-vs-muted">
                        Período
                        <select value={filters.periodo} onChange={(event) => filter({ periodo: event.target.value })} className={selectClass}>
                            {options.periods.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-xs text-vs-muted">
                        Origem
                        <select value={filters.origem} onChange={(event) => filter({ origem: event.target.value })} className={selectClass}>
                            <option value="">Todas as origens</option>
                            {options.origins.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-xs text-vs-muted">
                        Responsável
                        <select value={filters.responsavel} onChange={(event) => filter({ responsavel: event.target.value })} className={selectClass}>
                            <option value="">Todos</option>
                            <option value="nenhum">Sem responsável</option>
                            {options.assignees.map((member) => <option key={member.id} value={member.id}>{member.name}</option>)}
                        </select>
                    </label>
                </div>

                <section aria-labelledby="agora" className="grid gap-3">
                    <h2 id="agora" className="text-lg font-semibold text-vs-text">O que fazer agora</h2>
                    {recommendations.length === 0 ? (
                        <EmptyState>Nada pendente: nenhuma solicitação aguardando, parada ou sem valor, e nenhum agendamento nas próximas 48 horas.</EmptyState>
                    ) : (
                        <ul className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {recommendations.map((item) => (
                                <li key={item.key} className={`grid gap-1 rounded-xl border border-l-4 border-vs-border/70 bg-card p-4 shadow-sm ${toneClass[item.tone]}`}>
                                    <p className="text-xs font-medium uppercase tracking-wide text-vs-muted">{toneLabel[item.tone]}</p>
                                    <p className="font-semibold text-vs-text">{item.title}</p>
                                    <p className="text-sm text-vs-muted">{item.description}</p>
                                    {item.href.startsWith('#') ? (
                                        <a href={item.href} className="mt-1 text-sm font-medium text-vs-link hover:underline">{item.action} →</a>
                                    ) : (
                                        <Link href={item.href} className="mt-1 text-sm font-medium text-vs-link hover:underline">{item.action} →</Link>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section aria-labelledby="resultados" className="grid gap-3">
                    <h2 id="resultados" className="text-lg font-semibold text-vs-text">Resultados do atendimento</h2>
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <Metric label="Solicitações recebidas" value={current.requests} delta={comparison.requests} href={links.received} />
                        <Metric label="Convertidas em serviço" value={current.converted} hint="Recebidas no período e já concluídas" delta={comparison.converted} href={links.converted} />
                        <Metric
                            label="Taxa de conversão"
                            value={current.conversion === null ? 'Dados insuficientes' : `${current.conversion}%`}
                            hint={`${current.converted} de ${current.requests} recebidas`}
                            delta={comparison.conversion}
                            unit=" p.p."
                        />
                        <Metric label="Cancelados ou não realizados" value={current.cancelled} hint="Encerrados no período sem o serviço" delta={comparison.cancelled} inverse href={links.cancelled} />
                    </div>
                    <p className="text-xs text-vs-muted">
                        Taxa de conversão = solicitações recebidas no período que tiveram o serviço concluído ÷ todas as solicitações recebidas no período (o denominador), exibida a partir de {minRequestsForConversion} solicitações. {current.completed} {current.completed === 1 ? 'serviço foi concluído' : 'serviços foram concluídos'} no período, incluindo pedidos recebidos antes dele (<Link href={links.completed} className="text-vs-link hover:underline">ver</Link>).
                    </p>
                </section>

                <section aria-labelledby="financeiro" className="grid gap-3">
                    <div>
                        <h2 id="financeiro" className="text-lg font-semibold text-vs-text">Financeiro declarado</h2>
                        <p className="text-sm text-vs-muted">Valores que a empresa informou nos serviços concluídos em {period}. Não são comprovante de pagamento.</p>
                    </div>
                    <Card className="border-vs-border/70 bg-vs-surface/40 shadow-sm">
                        <CardContent className="grid gap-6 pt-6 sm:grid-cols-3">
                            <div>
                                <p className="text-sm text-vs-muted">Receita registrada</p>
                                <p className="mt-1 text-2xl font-semibold tabular-nums text-vs-text">{current.revenue ?? 'Não informada'}</p>
                                <p className="text-xs text-vs-muted">{current.revenue_known} {current.revenue_known === 1 ? 'serviço com valor' : 'serviços com valor'}</p>
                                <Delta value={comparison.revenue} />
                            </div>
                            <div>
                                <p className="text-sm text-vs-muted">Ticket médio</p>
                                <p className="mt-1 text-2xl font-semibold tabular-nums text-vs-text">{current.ticket ?? '—'}</p>
                                <p className="text-xs text-vs-muted">Só serviços com valor informado</p>
                                <Delta value={comparison.ticket} />
                            </div>
                            <div>
                                <p className="text-sm text-vs-muted">Concluídos sem valor</p>
                                <p className="mt-1 text-2xl font-semibold tabular-nums text-vs-text">{current.revenue_unknown}</p>
                                <p className="text-xs text-vs-muted">Fora da receita e do ticket, nunca contados como R$ 0,00</p>
                                {current.revenue_unknown > 0 && <Link href={links.without_amount} className="text-xs font-medium text-vs-link hover:underline">Informar valores →</Link>}
                            </div>
                        </CardContent>
                    </Card>
                    {shownRevenue.length > 0 && (
                        <Card className="overflow-hidden border-vs-border/70 shadow-sm">
                            <div className="overflow-x-auto" tabIndex={0} role="region" aria-label="Receita declarada por origem">
                                <table className="w-full min-w-[620px] text-left text-sm">
                                    <caption className="sr-only">Serviços concluídos no período por origem, com a receita informada, o ticket médio e os serviços sem valor</caption>
                                    <thead className="bg-vs-surface text-vs-muted">
                                        <tr>
                                            <th scope="col" className="px-4 py-3 font-medium">Origem</th>
                                            <th scope="col" className="px-4 py-3 font-medium">Concluídos</th>
                                            <th scope="col" className="px-4 py-3 font-medium">Receita informada</th>
                                            <th scope="col" className="px-4 py-3 font-medium">Ticket médio</th>
                                            <th scope="col" className="px-4 py-3 font-medium">Sem valor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {shownRevenue.map((row) => (
                                            <tr key={row.origin} className="border-t border-vs-border/70">
                                                <th scope="row" className="px-4 py-3 font-medium text-vs-text">{row.label}</th>
                                                <td className="px-4 py-3 tabular-nums text-vs-text">{row.completed}</td>
                                                <td className="px-4 py-3 tabular-nums text-vs-text">{row.revenue ?? 'Não informada'}</td>
                                                <td className="px-4 py-3 tabular-nums text-vs-text">{row.ticket ?? '—'}</td>
                                                <td className="px-4 py-3 tabular-nums text-vs-text">{row.revenue_unknown}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                    )}
                </section>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card className="border-vs-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-vs-text">Clientes</CardTitle>
                            <CardDescription>Quem enviou solicitações em {period}.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {customers.total === 0 ? (
                                <p className="text-sm text-vs-muted">Nenhum cliente enviou solicitações no período.</p>
                            ) : (
                                <dl className="grid grid-cols-3 gap-3 text-sm">
                                    <div><dt className="text-vs-muted">Total</dt><dd className="text-xl font-semibold tabular-nums text-vs-text">{customers.total}</dd></div>
                                    <div><dt className="text-vs-muted">Novos</dt><dd className="text-xl font-semibold tabular-nums text-vs-text">{customers.new}</dd></div>
                                    <div><dt className="text-vs-muted">Recorrentes</dt><dd className="text-xl font-semibold tabular-nums text-vs-text">{customers.recurring}</dd></div>
                                </dl>
                            )}
                            <p className="mt-3 text-xs text-vs-muted">Novo: primeira solicitação à empresa feita no período. Recorrente: já tinha pedido antes.</p>
                        </CardContent>
                    </Card>

                    <Card id="cancelamentos" className="scroll-mt-20 border-vs-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-vs-text">Cancelamentos e motivos</CardTitle>
                            <CardDescription>Encerrados em {period} sem o serviço, pelos motivos registrados pela equipe.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {closingReasons.length === 0 ? (
                                <p className="text-sm text-vs-muted">Nenhum cancelamento ou serviço não realizado no período.</p>
                            ) : (
                                <ul className="grid gap-2 text-sm">
                                    {closingReasons.map((row) => (
                                        <li key={`${row.status}-${row.reason}`} className="flex items-start justify-between gap-3 border-b border-vs-border/70 pb-2 last:border-0">
                                            <span className="text-vs-text">{row.reason} <span className="text-xs text-vs-muted">· {row.status}</span></span>
                                            <span className="font-semibold tabular-nums text-vs-text">{row.total}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {current.cancelled > 0 && <Link href={links.cancelled} className="mt-3 inline-block text-xs font-medium text-vs-link hover:underline">Ver as solicitações →</Link>}
                        </CardContent>
                    </Card>
                </div>

                <section aria-labelledby="origens" className="grid gap-3">
                    <h2 id="origens" className="text-lg font-semibold text-vs-text">De onde vêm os clientes em {period}</h2>
                    {shownOrigins.length === 0 ? (
                        <EmptyState>Nenhuma solicitação recebida no período. Com o site publicado e a empresa listada no Portal Regional, os pedidos aparecem aqui por origem.</EmptyState>
                    ) : (
                        <Card className="overflow-hidden border-vs-border/70 shadow-sm">
                            <div className="overflow-x-auto" tabIndex={0} role="region" aria-labelledby="origens">
                                <table className="w-full min-w-[600px] text-left text-sm">
                                    <caption className="sr-only">Solicitações recebidas em {period} por origem, e quantas delas foram agendadas, concluídas ou encerradas sem o serviço</caption>
                                    <thead className="bg-vs-surface text-vs-muted">
                                        <tr>
                                            <th scope="col" className="px-4 py-3 font-medium">Origem</th>
                                            <th scope="col" className="px-4 py-3 font-medium">Recebidas</th>
                                            <th scope="col" className="px-4 py-3 font-medium">Agendadas</th>
                                            <th scope="col" className="px-4 py-3 font-medium">Concluídas</th>
                                            <th scope="col" className="px-4 py-3 font-medium">Canceladas ou não realizadas</th>
                                            <th scope="col" className="px-4 py-3 font-medium">Recebidas em {previousPeriod}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {shownOrigins.map((row) => (
                                            <tr key={row.origin} className="border-t border-vs-border/70">
                                                <th scope="row" className="px-4 py-3 font-medium"><Link href={row.href} className="text-vs-link hover:underline">{row.label}</Link></th>
                                                <td className="px-4 py-3 tabular-nums text-vs-text">{row.received}</td>
                                                <td className="px-4 py-3 tabular-nums text-vs-text">{row.scheduled}</td>
                                                <td className="px-4 py-3 tabular-nums text-vs-text">{row.completed}</td>
                                                <td className="px-4 py-3 tabular-nums text-vs-text">{row.cancelled}</td>
                                                <td className="px-4 py-3 tabular-nums text-vs-muted">{previousOrigins.find((before) => before.origin === row.origin)?.received ?? 0}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                    )}
                    <p className="text-xs text-vs-muted">
                        Cada solicitação recebida no período é acompanhada até o fim: "agendadas" teve ao menos um agendamento; "concluídas" teve o serviço concluído. Conversas do atendimento IA continuam com a origem "Site / IA" mesmo quando passam para a equipe. Cliques e visitas não são contados.
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
                                    <table className="w-full min-w-[640px] text-left text-sm">
                                        <caption className="sr-only">Solicitações recebidas, serviços concluídos e receita informada por mês</caption>
                                        <thead className="text-vs-muted">
                                            <tr>
                                                <th scope="col" className="py-2 pr-4 font-medium">Mês</th>
                                                <th scope="col" className="py-2 pr-4 font-medium">Solicitações</th>
                                                <th scope="col" className="py-2 pr-4 font-medium">Concluídos</th>
                                                <th scope="col" className="py-2 font-medium">Receita informada</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {evolution.map((row) => (
                                                <tr key={row.month} className="border-t border-vs-border/70 text-vs-text">
                                                    <th scope="row" className="py-2 pr-4 font-normal">{row.month}</th>
                                                    <td className="py-2 pr-4">
                                                        <div className="flex items-center gap-2"><span className="w-8 text-right tabular-nums">{row.requests}</span><Bar value={row.requests} max={maxCount} label={`${row.month}: ${row.requests} solicitações`} /></div>
                                                    </td>
                                                    <td className="py-2 pr-4">
                                                        <div className="flex items-center gap-2"><span className="w-8 text-right tabular-nums">{row.completed}</span><Bar value={row.completed} max={maxCount} label={`${row.month}: ${row.completed} concluídos`} /></div>
                                                    </td>
                                                    <td className="py-2">
                                                        <div className="flex items-center gap-2">
                                                            <span className="min-w-24 text-right tabular-nums">{row.revenue ?? '—'}</span>
                                                            <Bar value={row.revenue_cents} max={maxRevenue} label={`${row.month}: ${row.revenue ?? 'sem valor informado'}`} />
                                                        </div>
                                                        {row.revenue_unknown > 0 && <span className="block text-xs text-vs-muted">{row.revenue_unknown} sem valor</span>}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </section>

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
            </div>
        </>
    );
}
