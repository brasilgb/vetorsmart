import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { selectClass } from '@/components/vetor-smart/field';
import { EmptyState } from '@/components/vetor-smart/empty-state';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { Card } from '@/components/ui/card';
import { index as growthIndex } from '@/routes/tenant/growth';

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
    funnel: { received: number; triaged: number; scheduled: number; completed: number; lost: number };
    stock: { requested: number; triaged: number; scheduled: number; in_progress: number };
    discovery: { discovery: string; label: string; received: number; completed: number; revenue: string | null; revenue_known: number }[];
    relationship: { new: number; served: number; returning: number; recurring: number; without_recent: number; frequency_days: number | null; days: number };
    customerLinks: { recurring: string; without_recent: string; new: string };
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

/**
 * Change against the previous period, always with the previous value as context. Without a previous base the change
 * is not computed (no artificial percentage). With "inverse", a rise is bad (cancellations) and shows in red.
 */
function Delta({ value, before, unit = '%', inverse = false }: { value: number | null; before?: string | number; unit?: string; inverse?: boolean }) {
    const context = before === undefined ? '' : ` (antes: ${before})`;

    if (value === null) {
        return <span className="text-xs text-vs-muted">sem base de comparação{context}</span>;
    }

    const good = inverse ? value < 0 : value > 0;
    const bad = inverse ? value > 0 : value < 0;
    const tone = good ? 'text-emerald-700 dark:text-emerald-400' : bad ? 'text-vs-danger' : 'text-vs-muted';

    return <span className={`text-xs font-medium ${tone}`}>{value > 0 ? '+' : ''}{value}{unit} vs. período anterior{context}</span>;
}

function Metric({ label, value, hint, children, href }: { label: string; value: ReactNode; hint?: string; children?: ReactNode; href?: string }) {
    const body = (
        <>
            <p className="text-sm text-vs-muted">{label}</p>
            <p className="mt-1 text-2xl font-semibold tabular-nums text-vs-text">{value}</p>
            {hint && <p className="text-xs text-vs-muted">{hint}</p>}
            {children}
        </>
    );

    return href ? (
        <Link href={href} className="block rounded-xl border border-vs-border/70 bg-card p-4 shadow-sm transition-colors hover:border-vs-blue focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-vs-blue">{body}</Link>
    ) : (
        <div className="rounded-xl border border-vs-border/70 bg-card p-4 shadow-sm">{body}</div>
    );
}

/** One horizontal bar of a single series; the number beside it is always written. */
function Bar({ value, max, label, strong = false }: { value: number; max: number; label: string; strong?: boolean }) {
    const width = max > 0 ? Math.max(value > 0 ? 2 : 0, Math.round((value / max) * 100)) : 0;

    return (
        <div className="h-3 w-full rounded-full bg-vs-surface" title={label}>
            <div className={`h-3 rounded-full ${strong ? 'bg-vs-blue dark:bg-sky-400' : 'bg-vs-blue/60 dark:bg-sky-400/60'}`} style={{ width: `${width}%` }} />
        </div>
    );
}

function Section({ id, title, description, children }: { id: string; title: string; description?: string; children: ReactNode }) {
    return (
        <section aria-labelledby={id} className="grid gap-3">
            <div>
                <h2 id={id} className="text-lg font-semibold text-vs-text">{title}</h2>
                {description && <p className="text-sm text-vs-muted">{description}</p>}
            </div>
            {children}
        </section>
    );
}

const toneClass: Record<Recommendation['tone'], string> = {
    attention: 'border-l-amber-500 dark:border-l-amber-400',
    warning: 'border-l-red-600 dark:border-l-red-400',
    info: 'border-l-vs-blue dark:border-l-sky-400',
};

/** Meu Crescimento: results of the company from its own records, organised to support decisions. */
export default function GrowthIndex(props: Props) {
    const { period, previousPeriod, current, previous, comparison, filters, options, links, recommendations, funnel, stock, discovery, relationship, customerLinks, closingReasons, revenueByOrigin, services, seo, evolution, hasActivity, minRequestsForConversion, origins, previousOrigins } = props;
    const filter = (changes: Partial<Props['filters']>) => router.get(growthIndex.url(), { ...filters, ...changes }, { preserveState: true, preserveScroll: true, replace: true });

    const stages = [
        { key: 'received', label: 'Oportunidades recebidas', value: funnel.received },
        { key: 'triaged', label: 'Passaram pela triagem', value: funnel.triaged },
        { key: 'scheduled', label: 'Chegaram a ser agendadas', value: funnel.scheduled },
        { key: 'completed', label: 'Serviço concluído', value: funnel.completed },
    ];
    // The bottleneck is the step where most of the cohort stopped (only with requests to measure).
    const drops = stages.slice(1).map((stage, index) => ({ key: stage.key, lost: stages[index].value - stage.value }));
    const bottleneck = funnel.received > 0 ? drops.reduce((worst, drop) => (drop.lost > worst.lost ? drop : worst), { key: '', lost: 0 }) : { key: '', lost: 0 };
    const percent = (value: number) => (funnel.received > 0 ? `${Math.round((value / funnel.received) * 100)}%` : '—');

    const shownOrigins = origins.filter((row) => row.received > 0);
    const shownRevenue = revenueByOrigin.filter((row) => row.completed > 0);
    const maxCount = Math.max(0, ...evolution.map((row) => Math.max(row.requests, row.completed)));
    const maxRevenue = Math.max(0, ...evolution.map((row) => row.revenue_cents));
    const maxOrigin = Math.max(0, ...shownOrigins.map((row) => row.received));

    return (
        <>
            <Head title="Meu Crescimento" />

            <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
                <PageHeader title="Meu Crescimento" description={`Resultados reais da sua empresa de ${period}, comparados com ${previousPeriod}. Visitas e posição em buscadores não são medidas pela VetorSmart.`} />

                <div className="grid gap-3 sm:grid-cols-3 lg:max-w-3xl" role="group" aria-label="Filtros dos indicadores">
                    <label className="grid gap-1 text-xs text-vs-muted">
                        Período
                        <select value={filters.periodo} onChange={(event) => filter({ periodo: event.target.value })} className={`${selectClass} h-10`}>
                            {options.periods.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-xs text-vs-muted">
                        Canal
                        <select value={filters.origem} onChange={(event) => filter({ origem: event.target.value })} className={`${selectClass} h-10`}>
                            <option value="">Todos os canais</option>
                            {options.origins.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-xs text-vs-muted">
                        Responsável
                        <select value={filters.responsavel} onChange={(event) => filter({ responsavel: event.target.value })} className={`${selectClass} h-10`}>
                            <option value="">Todos</option>
                            <option value="nenhum">Sem responsável</option>
                            {options.assignees.map((member) => <option key={member.id} value={member.id}>{member.name}</option>)}
                        </select>
                    </label>
                </div>

                <Section id="resumo" title="Resumo do crescimento">
                    <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
                        <Metric label="Oportunidades recebidas" value={current.requests} href={links.received}><Delta value={comparison.requests} before={previous.requests} /></Metric>
                        <Metric label="Serviços concluídos" value={current.completed} hint="Concluídos no período" href={links.completed}><Delta value={comparison.completed} before={previous.completed} /></Metric>
                        <Metric label="Taxa de conversão" value={current.conversion === null ? 'Dados insuficientes' : `${current.conversion}%`} hint={`${current.converted} de ${current.requests} oportunidades viraram serviço`} href={links.converted}>
                            <Delta value={comparison.conversion} before={previous.conversion === null ? '—' : `${previous.conversion}%`} unit=" p.p." />
                        </Metric>
                        <Metric label="Canceladas ou não realizadas" value={current.cancelled} href={links.cancelled}><Delta value={comparison.cancelled} before={previous.cancelled} inverse /></Metric>
                        <Metric label="Clientes novos" value={relationship.new} hint="Primeiro contato no período" href={customerLinks.new} />
                        <Metric label="Voltaram a contratar" value={relationship.returning} hint={`De ${relationship.served} clientes atendidos no período`} href={customerLinks.recurring} />
                        <Metric label="Receita registrada" value={current.revenue ?? 'Não informada'} hint={`${current.revenue_known} ${current.revenue_known === 1 ? 'serviço com valor' : 'serviços com valor'}`}>
                            <Delta value={comparison.revenue} before={previous.revenue ?? '—'} />
                        </Metric>
                        <Metric label="Ticket médio" value={current.ticket ?? '—'} hint="Só serviços com valor informado"><Delta value={comparison.ticket} before={previous.ticket ?? '—'} /></Metric>
                    </div>
                    <p className="text-xs text-vs-muted">
                        Conversão = oportunidades recebidas no período que tiveram o serviço concluído ÷ todas as recebidas no período, exibida a partir de {minRequestsForConversion} oportunidades. A variação só é calculada quando o período anterior tem dados.
                    </p>
                </Section>

                {recommendations.length > 0 && (
                    <Section id="agora" title="O que fazer agora">
                        <ul className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {recommendations.map((item) => (
                                <li key={item.key} className={`grid gap-1 rounded-xl border border-l-4 border-vs-border/70 bg-card p-4 shadow-sm ${toneClass[item.tone]}`}>
                                    <p className="font-semibold text-vs-text">{item.title}</p>
                                    <p className="text-sm text-vs-muted">{item.description}</p>
                                    {item.href.startsWith('#') ? (
                                        <a href={item.href} className="mt-1 inline-flex min-h-10 items-center text-sm font-medium text-vs-link hover:underline">{item.action} →</a>
                                    ) : (
                                        <Link href={item.href} className="mt-1 inline-flex min-h-10 items-center text-sm font-medium text-vs-link hover:underline">{item.action} →</Link>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </Section>
                )}

                <Section id="evolucao" title="Evolução dos últimos seis meses" description="Oportunidades recebidas, serviços concluídos e receita registrada, mês a mês.">
                    {!hasActivity ? (
                        <EmptyState>Ainda não há oportunidades, agendamentos realizados ou serviços concluídos nos últimos seis meses.</EmptyState>
                    ) : (
                        <Card className="gap-0 divide-y divide-vs-border/70 border-vs-border/70 p-0 shadow-sm">
                            {evolution.map((row) => (
                                <div key={row.month} className="grid gap-2 p-4 sm:grid-cols-[80px_1fr_1fr_1fr] sm:items-center sm:gap-4">
                                    <p className="font-medium text-vs-text">{row.month}</p>
                                    <div className="grid gap-1 text-sm"><span className="text-vs-muted">Oportunidades: <strong className="tabular-nums text-vs-text">{row.requests}</strong></span><Bar value={row.requests} max={maxCount} label={`${row.month}: ${row.requests} oportunidades`} /></div>
                                    <div className="grid gap-1 text-sm"><span className="text-vs-muted">Concluídos: <strong className="tabular-nums text-vs-text">{row.completed}</strong></span><Bar value={row.completed} max={maxCount} label={`${row.month}: ${row.completed} concluídos`} strong /></div>
                                    <div className="grid gap-1 text-sm">
                                        <span className="text-vs-muted">Receita: <strong className="tabular-nums text-vs-text">{row.revenue ?? '—'}</strong>{row.revenue_unknown > 0 ? ` · ${row.revenue_unknown} sem valor` : ''}</span>
                                        <Bar value={row.revenue_cents} max={maxRevenue} label={`${row.month}: ${row.revenue ?? 'sem valor informado'}`} />
                                    </div>
                                </div>
                            ))}
                        </Card>
                    )}
                </Section>

                <Section id="funil" title="Funil de conversão" description={`Acompanha as ${funnel.received} oportunidades recebidas de ${period} até o fim. Cada etapa conta as mesmas oportunidades que chegaram até ela.`}>
                    {funnel.received === 0 ? (
                        <EmptyState>Nenhuma oportunidade recebida no período.</EmptyState>
                    ) : (
                        <Card className="grid gap-4 border-vs-border/70 p-5 shadow-sm">
                            <ol className="grid gap-4">
                                {stages.map((stage, index) => {
                                    const drop = drops.find((item) => item.key === stage.key);
                                    const isBottleneck = bottleneck.key === stage.key && bottleneck.lost > 0;

                                    return (
                                        <li key={stage.key} className="grid gap-1">
                                            <div className="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                                                <span className="font-medium text-vs-text">{index + 1}. {stage.label}</span>
                                                <span className="tabular-nums text-vs-text"><strong>{stage.value}</strong> <span className="text-vs-muted">({percent(stage.value)})</span></span>
                                            </div>
                                            <Bar value={stage.value} max={funnel.received} label={`${stage.label}: ${stage.value}`} strong={stage.key === 'completed'} />
                                            {drop && drop.lost > 0 && (
                                                <p className={`text-xs ${isBottleneck ? 'font-medium text-vs-danger' : 'text-vs-muted'}`}>
                                                    {drop.lost} {drop.lost === 1 ? 'parou' : 'pararam'} antes desta etapa{isBottleneck ? ' — maior perda do funil' : ''}
                                                </p>
                                            )}
                                        </li>
                                    );
                                })}
                            </ol>
                            <p className="text-xs text-vs-muted">
                                Destas oportunidades, {funnel.lost} {funnel.lost === 1 ? 'foi encerrada' : 'foram encerradas'} sem o serviço (canceladas ou não realizadas); as demais ainda estão em andamento. <a href="#cancelamentos" className="text-vs-link hover:underline">Ver motivos</a>.
                            </p>
                        </Card>
                    )}
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4" aria-label="Situação atual">
                        {[
                            { label: 'Aguardando triagem agora', value: stock.requested, href: '/solicitacoes?status=requested' },
                            { label: 'Aguardando agendamento', value: stock.triaged, href: '/solicitacoes?status=triaged' },
                            { label: 'Agendadas', value: stock.scheduled, href: '/solicitacoes?status=scheduled' },
                            { label: 'Em andamento', value: stock.in_progress, href: '/solicitacoes?status=in_progress' },
                        ].map((item) => (
                            <Link key={item.label} href={item.href} className="rounded-xl border border-dashed border-vs-border bg-card p-3 hover:border-vs-blue">
                                <span className="block text-xl font-semibold tabular-nums text-vs-text">{item.value}</span>
                                <span className="text-xs text-vs-muted">{item.label}</span>
                            </Link>
                        ))}
                    </div>
                    <p className="text-xs text-vs-muted">Os quadros tracejados mostram a situação de hoje (o que está em cada etapa agora, de qualquer período). Não são taxas de conversão.</p>
                </Section>

                <Section id="origens" title="Origem das oportunidades" description={`Recebidas de ${period}.`}>
                    <div className="grid gap-4 lg:grid-cols-2">
                        <Card className="grid content-start gap-3 border-vs-border/70 p-5 shadow-sm">
                            <h3 className="font-semibold text-vs-text">Como o cliente encontrou a empresa</h3>
                            <ul className="grid gap-3">
                                {discovery.map((row) => (
                                    <li key={row.discovery} className="grid gap-1 text-sm">
                                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                                            <span className="font-medium text-vs-text">{row.label}</span>
                                            <span className="tabular-nums text-vs-text"><strong>{row.received}</strong> <span className="text-vs-muted">· {row.completed} concluídas</span></span>
                                        </div>
                                        <Bar value={row.received} max={Math.max(...discovery.map((item) => item.received))} label={`${row.label}: ${row.received}`} />
                                    </li>
                                ))}
                            </ul>
                            <p className="text-xs text-vs-muted">"Portal Regional" só quando a chegada pelo portal foi confirmada pelo link assinado. As demais aparecem como não identificadas: nada é presumido.</p>
                        </Card>
                        <Card className="grid content-start gap-3 border-vs-border/70 p-5 shadow-sm">
                            <h3 className="font-semibold text-vs-text">Por onde a solicitação chegou (canal)</h3>
                            {shownOrigins.length === 0 ? (
                                <p className="text-sm text-vs-muted">Nenhuma oportunidade no período.</p>
                            ) : (
                                <ul className="grid gap-3">
                                    {shownOrigins.map((row) => (
                                        <li key={row.origin} className="grid gap-1 text-sm">
                                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                                <Link href={row.href} className="font-medium text-vs-link hover:underline">{row.label}</Link>
                                                <span className="tabular-nums text-vs-text">
                                                    <strong>{row.received}</strong> <span className="text-vs-muted">· {row.scheduled} agendadas · {row.completed} concluídas · antes: {previousOrigins.find((item) => item.origin === row.origin)?.received ?? 0}</span>
                                                </span>
                                            </div>
                                            <Bar value={row.received} max={maxOrigin} label={`${row.label}: ${row.received}`} />
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <p className="text-xs text-vs-muted">O canal é por onde a solicitação foi feita (formulário do site, formulário vindo do portal, atendimento IA, painel). Conversas da IA continuam como "Site / IA" quando passam para a equipe.</p>
                        </Card>
                    </div>
                </Section>

                <Section id="receita" title="Receita atribuível" description={`Valores que a empresa registrou nos serviços concluídos de ${period}. Não são pagamentos recebidos, saldo bancário nem faturamento fiscal.`}>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <Metric label="Valor registrado" value={current.revenue ?? 'Não informado'} hint={`${current.revenue_known} ${current.revenue_known === 1 ? 'serviço concluído com valor' : 'serviços concluídos com valor'}`} />
                        <Metric label="Ticket médio" value={current.ticket ?? '—'} hint="Valor registrado ÷ serviços com valor" />
                        <Metric label="Concluídos sem valor" value={current.revenue_unknown} hint="Fora da soma e do ticket; nunca contados como R$ 0,00" href={current.revenue_unknown > 0 ? links.without_amount : undefined} />
                    </div>
                    {shownRevenue.length > 0 && (
                        <Card className="gap-0 divide-y divide-vs-border/70 border-vs-border/70 p-0 shadow-sm">
                            {shownRevenue.map((row) => (
                                <div key={row.origin} className="flex flex-wrap items-baseline justify-between gap-2 p-4 text-sm">
                                    <span className="font-medium text-vs-text">{row.label}</span>
                                    <span className="tabular-nums text-vs-text">
                                        <strong>{row.revenue ?? 'Não informada'}</strong>
                                        <span className="text-vs-muted"> · {row.completed} concluídos · ticket {row.ticket ?? '—'}{row.revenue_unknown > 0 ? ` · ${row.revenue_unknown} sem valor` : ''}</span>
                                    </span>
                                </div>
                            ))}
                            {discovery.find((row) => row.discovery === 'portal' && row.completed > 0) && (
                                <p className="p-4 text-xs text-vs-muted">
                                    Clientes que chegaram pelo Portal Regional (por qualquer canal): {discovery.find((row) => row.discovery === 'portal')?.revenue ?? 'sem valor informado'} em serviços concluídos das oportunidades recebidas no período.
                                </p>
                            )}
                        </Card>
                    )}
                </Section>

                <Section id="relacionamento" title="Relacionamento e recorrência" description="Clientes de toda a empresa (os filtros de canal e responsável não se aplicam aqui).">
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <Metric label="Clientes recorrentes" value={relationship.recurring} hint="2 ou mais serviços concluídos" href={customerLinks.recurring} />
                        <Metric label="Atendidos no período" value={relationship.served} hint={`${relationship.returning} voltaram a contratar`} />
                        <Metric label="Sem atendimento recente" value={relationship.without_recent} hint={`Nenhum serviço nos últimos ${relationship.days} dias`} href={customerLinks.without_recent} />
                        <Metric label="Frequência de contratação" value={relationship.frequency_days === null ? '—' : `~${relationship.frequency_days} dias`} hint="Intervalo médio entre serviços dos clientes recorrentes" />
                    </div>
                    <p className="text-xs text-vs-muted">"Sem atendimento recente" descreve apenas o período analisado e não significa que o cliente deixou a empresa.</p>
                </Section>

                <Section id="cancelamentos" title="Cancelamentos e motivos" description={`Encerradas de ${period} sem o serviço, pelos motivos registrados pela equipe.`}>
                    {closingReasons.length === 0 ? (
                        <p className="text-sm text-vs-muted">Nenhum cancelamento ou serviço não realizado no período.</p>
                    ) : (
                        <Card className="gap-0 divide-y divide-vs-border/70 border-vs-border/70 p-0 shadow-sm">
                            {closingReasons.map((row) => (
                                <div key={`${row.status}-${row.reason}`} className="flex items-start justify-between gap-3 p-4 text-sm">
                                    <span className="text-vs-text">{row.reason} <span className="text-xs text-vs-muted">· {row.status}</span></span>
                                    <span className="font-semibold tabular-nums text-vs-text">{row.total}</span>
                                </div>
                            ))}
                            <Link href={links.cancelled} className="flex min-h-11 items-center px-4 text-sm font-medium text-vs-link hover:underline">Ver as solicitações</Link>
                        </Card>
                    )}
                </Section>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card className="grid gap-2 border-vs-border/70 p-5 shadow-sm">
                        <h2 className="font-semibold text-vs-text">Serviços</h2>
                        <p className="text-sm text-vs-muted">{services.active} ativos no cadastro · {services.site === 'published' ? `${services.on_site} exibidos no site publicado` : 'site ainda não publicado'}.</p>
                    </Card>
                    <Card className="grid gap-2 border-vs-border/70 p-5 shadow-sm">
                        <h2 className="font-semibold text-vs-text">Seu site nos buscadores</h2>
                        <ul className="grid gap-1 text-sm">
                            <li className={seo.title ? 'text-emerald-700 dark:text-emerald-400' : 'text-vs-muted'}>{seo.title ? '✓' : '—'} Título para buscadores</li>
                            <li className={seo.description ? 'text-emerald-700 dark:text-emerald-400' : 'text-vs-muted'}>{seo.description ? '✓' : '—'} Descrição para buscadores</li>
                            <li className={seo.indexable ? 'text-emerald-700 dark:text-emerald-400' : 'text-vs-muted'}>{seo.indexable ? '✓ Liberado para indexação' : '— Não liberado para indexação'}</li>
                        </ul>
                        <p className="text-xs text-vs-muted">A VetorSmart não mede se o Google já indexou as páginas.</p>
                    </Card>
                </div>
            </div>
        </>
    );
}
