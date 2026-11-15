import { Globe, MousePointerClick, Sparkles, BadgeCheck, type LucideIcon } from 'lucide-react';
import { landingCard } from './landing-layout';
import { SectionShell } from './section-shell';

const steps: { title: string; text: string; footer: string; icon: LucideIcon }[] = [
    {
        title: 'Presença digital imediata',
        text: 'Seu negócio ganha um site moderno e rápido, com seus serviços detalhados e contatos prontos.',
        footer: 'Google e redes sociais',
        icon: Globe,
    },
    {
        title: 'Cliente solicita o serviço',
        text: 'Visitantes acessam seu site e pedem o serviço pelo chat de atendimento, a qualquer hora.',
        footer: 'Captação ativa 24h',
        icon: MousePointerClick,
    },
    {
        title: 'Organização automática',
        text: 'O atendimento virtual identifica o serviço, registra o contato e reserva um horário livre na sua agenda.',
        footer: 'Sem perda de tempo',
        icon: Sparkles,
    },
    {
        title: 'Execução e crescimento',
        text: 'Você executa o trabalho com tranquilidade e acompanha no painel as solicitações, os serviços concluídos e a taxa de conversão.',
        footer: 'Indicadores reais',
        icon: BadgeCheck,
    },
];

/** "Como funciona": four steps in Stitch cards, one per stage of the client journey. */
export function JourneySection() {
    return (
        <SectionShell
            id="como-funciona"
            tone="tint"
            eyebrow="Jornada fluida"
            title="Do primeiro contato ao serviço concluído."
            lead="Um fluxo simples e direto para sua empresa rodar com máxima eficiência."
        >
            <ol className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                {steps.map((step, index) => {
                    const Icon = step.icon;

                    return (
                        <li key={step.title} className={`${landingCard} flex flex-col justify-between p-6 transition-shadow duration-200 hover:shadow-md motion-reduce:transition-none`}>
                            <div>
                                <div className="mb-5 flex items-center justify-between">
                                    <span aria-hidden="true" className="flex size-9 items-center justify-center rounded-full bg-vs-blue-hover font-display text-sm font-bold text-white shadow-sm">
                                        {String(index + 1).padStart(2, '0')}
                                    </span>
                                    <Icon aria-hidden="true" className="size-5 text-vs-muted" />
                                </div>
                                <h3 className="vs-title-card text-vs-text">{step.title}</h3>
                                <p className="mt-2 text-sm leading-relaxed text-vs-muted">{step.text}</p>
                            </div>
                            <p className="mt-5 border-t border-vs-line pt-3 text-xs font-semibold text-vs-blue-hover">{step.footer}</p>
                        </li>
                    );
                })}
            </ol>
        </SectionShell>
    );
}
