import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { show as subscriptionShow } from '@/routes/rootadmin/subscriptions';
import { show as tenantShow } from '@/routes/rootadmin/tenants';

type Row = {
    id: number;
    name: string;
    is_homolog: boolean;
    assistant_enabled: boolean;
    messages: number;
    provider_calls: number;
    provider_failures: number;
    tokens: number;
    credits_consumed: number;
    credits_uncovered: number;
    included_balance: number | null;
    purchased_balance: number | null;
    has_subscription: boolean;
};

type Purchase = { id: number; tenant: string; subscription_id: number | null; package: string; credits: number; price: string; at: string | null };

type Props = {
    period: string;
    provider: string;
    franchise: number | null;
    costConfigured: boolean;
    totals: { assistant_enabled: number; messages: number; provider_calls: number; provider_failures: number; tokens: number; credits_consumed: number };
    rows: Row[];
    purchases: Purchase[];
};

const number = new Intl.NumberFormat('pt-BR');

function Metric({ label, value }: { label: string; value: number | string }) {
    return (
        <Card className="border-vs-border/70 p-4 shadow-sm">
            <p className="text-sm text-vs-muted">{label}</p>
            <p className="mt-1 text-2xl font-semibold text-vs-text">{typeof value === 'number' ? number.format(value) : value}</p>
        </Card>
    );
}

/** Real usage of the virtual attendant and AI credits per company in the current month. Read-only. */
export default function AiIndex({ period, provider, franchise, costConfigured, totals, rows, purchases }: Props) {
    return (
        <>
            <Head title="IA e Créditos · RootAdmin" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-vs-text">IA e Créditos</h1>
                    <p className="text-sm text-vs-muted">
                        Uso real do atendimento virtual em {period}, saldos e consumo de créditos. Empresas de homologação aparecem marcadas e ficam fora dos
                        totais.
                    </p>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    <Metric label="Empresas com atendimento ligado" value={totals.assistant_enabled} />
                    <Metric label="Mensagens no mês" value={totals.messages} />
                    <Metric label="Chamadas ao provedor" value={totals.provider_calls} />
                    <Metric label="Falhas do provedor" value={totals.provider_failures} />
                    <Metric label="Tokens" value={totals.tokens} />
                    <Metric label="Créditos consumidos" value={totals.credits_consumed} />
                </div>

                <Card className="border-vs-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-vs-text">Configuração</CardTitle>
                        <CardDescription>Somente leitura. Franquia e pacotes são definidos em “Plano e Efí”.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 text-sm sm:grid-cols-3">
                            <div><dt className="text-vs-muted">Provedor de IA</dt><dd className="font-medium text-vs-text">{provider === 'none' ? 'Nenhum (só o fluxo determinístico)' : provider}</dd></div>
                            <div><dt className="text-vs-muted">Franquia mensal do plano</dt><dd className="font-medium text-vs-text">{franchise === null ? 'Não definida (créditos não aplicados)' : `${number.format(franchise)} créditos`}</dd></div>
                            <div><dt className="text-vs-muted">Custo estimado</dt><dd className="font-medium text-vs-text">{costConfigured ? 'Calculado pelo preço configurado' : 'Indisponível: sem preço do provedor cadastrado'}</dd></div>
                        </dl>
                    </CardContent>
                </Card>

                <section aria-labelledby="empresas" className="grid gap-3">
                    <h2 id="empresas" className="text-lg font-semibold text-vs-text">Por empresa</h2>
                    <Card className="overflow-hidden border-vs-border/70 shadow-sm">
                        <div className="overflow-x-auto" tabIndex={0} role="region" aria-label="Uso de IA por empresa">
                            <table className="w-full min-w-[900px] text-left text-sm">
                                <thead className="bg-vs-surface text-vs-muted">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Empresa</th>
                                        <th className="px-4 py-3 font-medium">Atendimento</th>
                                        <th className="px-4 py-3 text-right font-medium">Mensagens</th>
                                        <th className="px-4 py-3 text-right font-medium">Chamadas / falhas</th>
                                        <th className="px-4 py-3 text-right font-medium">Tokens</th>
                                        <th className="px-4 py-3 text-right font-medium">Créditos no mês</th>
                                        <th className="px-4 py-3 text-right font-medium">Saldo (franquia / comprados)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.length === 0 && <tr><td colSpan={7} className="px-4 py-6 text-center text-vs-muted">Nenhum uso de IA registrado ainda.</td></tr>}
                                    {rows.map((row) => (
                                        <tr key={row.id} className="border-t border-vs-border/70">
                                            <td className="px-4 py-3">
                                                <Link href={tenantShow.url({ tenant: row.id })} className="text-vs-link hover:underline">{row.name}</Link>
                                                {row.is_homolog && <Badge variant="outline" className="ml-2 border-vs-border text-vs-muted">Homologação</Badge>}
                                            </td>
                                            <td className="px-4 py-3 text-vs-text">{row.assistant_enabled ? 'Ligado' : 'Desligado'}</td>
                                            <td className="px-4 py-3 text-right text-vs-text">{number.format(row.messages)}</td>
                                            <td className="px-4 py-3 text-right text-vs-text">{number.format(row.provider_calls)} / {number.format(row.provider_failures)}</td>
                                            <td className="px-4 py-3 text-right text-vs-text">{number.format(row.tokens)}</td>
                                            <td className="px-4 py-3 text-right text-vs-text">
                                                {number.format(row.credits_consumed)}
                                                {row.credits_uncovered > 0 && <span className="block text-xs text-vs-danger">{number.format(row.credits_uncovered)} sem saldo</span>}
                                            </td>
                                            <td className="px-4 py-3 text-right text-vs-text">
                                                {row.included_balance === null ? '—' : `${number.format(row.included_balance)} / ${number.format(row.purchased_balance ?? 0)}`}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </section>

                <section aria-labelledby="compras" className="grid gap-3">
                    <h2 id="compras" className="text-lg font-semibold text-vs-text">Compras aguardando confirmação</h2>
                    {purchases.length === 0 ? (
                        <p className="text-sm text-vs-muted">Nenhuma compra aguardando. A compra de créditos está desligada enquanto a cobrança não estiver em vigor.</p>
                    ) : (
                        <ul className="grid gap-2 text-sm">
                            {purchases.map((purchase) => (
                                <li key={purchase.id} className="flex flex-wrap justify-between gap-2 border-t border-vs-border/70 pt-2 text-vs-text">
                                    <span>#{purchase.id} · {purchase.at} · {purchase.tenant} · {purchase.package} · {number.format(purchase.credits)} créditos · {purchase.price}</span>
                                    {purchase.subscription_id !== null && (
                                        <Link href={subscriptionShow.url({ subscription: purchase.subscription_id })} className="text-vs-link hover:underline">Abrir assinatura</Link>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}
