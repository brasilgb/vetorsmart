import { Head } from '@inertiajs/react';
import { CheckCircle2, Circle } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type Metrics = {
    customers: number;
    services: number;
    professionals: number;
};

type ChecklistItem = {
    key: string;
    label: string;
    done: boolean;
};

type Props = {
    metrics: Metrics;
    checklist: ChecklistItem[];
    needsSetup: boolean;
};

export default function Dashboard({ metrics, checklist, needsSetup }: Props) {
    const indicators = [
        { label: 'Clientes ativos', value: metrics.customers },
        { label: 'Serviços ativos', value: metrics.services },
        { label: 'Profissionais ativos', value: metrics.professionals },
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

                <p className="text-xs text-vs-muted">
                    Solicitações e agendamentos aparecerão aqui quando os módulos forem implementados.
                </p>
            </div>
        </>
    );
}
