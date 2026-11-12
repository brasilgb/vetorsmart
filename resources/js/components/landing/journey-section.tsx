import { CalendarCheck, Globe, MousePointerClick, Repeat, type LucideIcon } from 'lucide-react';
import { landingCard } from './landing-layout';
import { SectionShell } from './section-shell';

type Step = { name: string; text: string; footnote: string; icon: LucideIcon };

/** The flow in four stages, as in the Stitch landing. Each stage describes what the product does today. */
const steps: Step[] = [
    {
        name: 'Presença digital',
        text: 'Seu negócio ganha um site moderno com os serviços detalhados e os contatos prontos para quem procura.',
        footnote: 'Google e redes sociais',
        icon: Globe,
    },
    {
        name: 'Cliente solicita o serviço',
        text: 'O visitante encontra seus serviços no site e entra em contato a qualquer hora do dia.',
        footnote: 'Site disponível 24 horas',
        icon: MousePointerClick,
    },
    {
        name: 'Organização da agenda',
        text: 'Serviços, profissionais e horários de trabalho ficam reunidos para encaixar cada atendimento.',
        footnote: 'Um cadastro para tudo',
        icon: CalendarCheck,
    },
    {
        name: 'Execução e fidelização',
        text: 'O histórico do cliente fica à mão para o próximo atendimento, e você acompanha a evolução do negócio.',
        footnote: 'Histórico do cliente',
        icon: Repeat,
    },
];

/** "Como funciona": four numbered cards. Desktop shows them on one line; tablets use two columns. */
export function JourneySection() {
    return (
        <SectionShell
            id="como-funciona"
            tone="tint"
            eyebrow="Jornada fluida"
            title="Do primeiro contato ao serviço concluído."
            lead="Um fluxo simples e direto para sua empresa rodar com eficiência."
        >
            <ol className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                {steps.map((step, index) => {
                    const Icon = step.icon;

                    return (
                        <li key={step.name} className={`${landingCard} flex flex-col p-6`}>
                            <div className="flex items-center justify-between">
                                <span className="flex size-9 items-center justify-center rounded-full bg-vs-blue-hover text-sm font-bold tabular-nums text-white shadow-sm">
                                    {String(index + 1).padStart(2, '0')}
                                </span>
                                <Icon aria-hidden="true" className="size-5 text-slate-500" strokeWidth={1.75} />
                            </div>
                            <h3 className="vs-title-card mt-4 text-vs-text">{step.name}</h3>
                            <p className="mt-2 text-sm leading-relaxed text-vs-muted">{step.text}</p>
                            <p className="mt-auto pt-4">
                                <span className="block border-t border-slate-100 pt-2 text-xs font-semibold text-vs-blue-hover">{step.footnote}</span>
                            </p>
                        </li>
                    );
                })}
            </ol>
        </SectionShell>
    );
}
