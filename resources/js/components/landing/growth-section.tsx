import { CheckCircle2, Circle } from 'lucide-react';
import { SectionShell, StatusBadge } from './section-shell';

/** Indicators the panel is designed to show. None has a figure: the module is not delivered yet. */
const indicators = [
    'Origem dos contatos do site',
    'Propostas e conversões',
    'Receita registrada e recebida',
    'Clientes recorrentes',
    'Solicitações de avaliação no Google',
];

/**
 * Conceptual presentation of Meu Crescimento. The chart is an illustration with no axes or values,
 * and it is labelled as such, so no invented result can be read as real.
 */
export function GrowthSection() {
    return (
        <SectionShell
            id="meu-crescimento"
            eyebrow="Meu Crescimento"
            title="Saiba de onde vêm suas oportunidades e acompanhe a evolução da sua empresa."
            lead="Um painel voltado a resultados: cada indicador tem origem conhecida e mostra o que já aconteceu, sem promessas de faturamento."
        >
            <div className="grid items-center gap-12 lg:grid-cols-[1fr_1.05fr] lg:gap-16">
                <div>
                    <StatusBadge status="development" />
                    <p className="mt-6 text-sm font-semibold uppercase tracking-[0.14em] text-vs-muted">Indicadores previstos</p>
                    <ul className="mt-4 grid gap-3">
                        {indicators.map((indicator) => (
                            <li key={indicator} className="flex items-center gap-3 rounded-xl border border-vs-line bg-white px-5 py-4 text-vs-text">
                                <Circle aria-hidden="true" className="size-4 shrink-0 text-vs-muted" />
                                {indicator}
                            </li>
                        ))}
                    </ul>
                    <p className="mt-5 flex items-start gap-2 text-sm leading-relaxed text-vs-muted">
                        <CheckCircle2 aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-vs-ink-green" />
                        Quando um indicador não tiver fonte confiável, ele aparecerá como indisponível, e não com um número estimado.
                    </p>
                </div>

                <figure className="rounded-2xl border border-vs-line bg-vs-surface p-6 sm:p-8">
                    <svg viewBox="0 0 400 200" role="img" aria-label="Ilustração conceitual de uma linha de evolução, sem valores" className="h-auto w-full">
                        <defs>
                            <linearGradient id="growth-fill" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stopColor="#2563EB" stopOpacity="0.22" />
                                <stop offset="100%" stopColor="#2563EB" stopOpacity="0" />
                            </linearGradient>
                        </defs>
                        <path d="M0 170 C 60 150, 90 160, 130 120 S 200 110, 240 80 S 320 60, 400 26 L400 200 L0 200 Z" fill="url(#growth-fill)" />
                        <path d="M0 170 C 60 150, 90 160, 130 120 S 200 110, 240 80 S 320 60, 400 26" fill="none" stroke="#2563EB" strokeWidth="3" strokeLinecap="round" />
                        {[130, 240, 400].map((cx, index) => {
                            const cy = [120, 80, 26][index];

                            return <circle key={cx} cx={cx} cy={cy} r="5" fill="#FFFFFF" stroke="#2563EB" strokeWidth="2.5" />;
                        })}
                    </svg>
                    <figcaption className="mt-5 text-center text-xs leading-relaxed text-vs-muted">
                        Ilustração conceitual. A linha mostra apenas a ideia de evolução; não representa dados de nenhuma empresa.
                    </figcaption>
                </figure>
            </div>
        </SectionShell>
    );
}
