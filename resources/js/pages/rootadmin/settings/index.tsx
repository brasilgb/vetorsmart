import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Item = { label: string; value: string; state: 'configured' | 'disabled' | 'unavailable'; note: string | null };

type Section = { title: string; items: Item[] };

const stateStyle: Record<Item['state'], { label: string; tone: string }> = {
    configured: { label: 'Configurado', tone: 'border-emerald-600 text-emerald-700 dark:text-emerald-400' },
    disabled: { label: 'Desabilitado', tone: 'border-vs-border text-vs-muted' },
    unavailable: { label: 'Indisponível', tone: 'border-amber-500 text-amber-700 dark:text-amber-400' },
};

/** Parameters in force on the platform, read-only. Secrets are never shown: only whether each one is set. */
export default function GlobalSettings({ sections }: { sections: Section[] }) {
    return (
        <>
            <Head title="Configurações Globais · RootAdmin" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-vs-text">Configurações Globais</h1>
                    <p className="text-sm text-vs-muted">
                        Parâmetros em vigor, somente leitura. Senhas, tokens e credenciais nunca são exibidos; esta tela não altera o ambiente do servidor.
                    </p>
                    <ul className="mt-3 flex flex-wrap gap-2 text-xs" aria-label="Legenda">
                        {Object.entries(stateStyle).map(([key, style]) => (
                            <li key={key}>
                                <Badge variant="outline" className={style.tone}>{style.label}</Badge>
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    {sections.map((section) => (
                        <Card key={section.title} className="border-vs-border/70 shadow-sm">
                            <CardHeader>
                                <CardTitle className="text-vs-text">{section.title}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid gap-3 text-sm">
                                    {section.items.map((item) => (
                                        <div key={item.label} className="grid gap-1 border-t border-vs-border/70 pt-3 first:border-t-0 first:pt-0 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start sm:gap-3">
                                            <div className="min-w-0">
                                                <dt className="text-vs-muted">{item.label}</dt>
                                                <dd className="font-medium break-words text-vs-text">{item.value}</dd>
                                                {item.note && <dd className="text-xs text-vs-muted">{item.note}</dd>}
                                            </div>
                                            <dd>
                                                <Badge variant="outline" className={stateStyle[item.state].tone}>{stateStyle[item.state].label}</Badge>
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}
