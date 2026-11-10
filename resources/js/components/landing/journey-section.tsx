import { ClipboardList, Handshake, Heart, Megaphone, MessageCircle, TrendingUp, type LucideIcon } from 'lucide-react';
import { SectionShell } from './section-shell';

type Step = { name: string; text: string; icon: LucideIcon };

/** The six stages of the customer journey, in the order the product vision defines them. */
const steps: Step[] = [
    { name: 'Atrair', text: 'Um site profissional, com SEO, pensado para ser encontrado.', icon: Megaphone },
    { name: 'Atender', text: 'Contatos registrados e acompanhados, sem perder ninguém pelo caminho.', icon: MessageCircle },
    { name: 'Converter', text: 'Do primeiro contato ao agendamento, reaproveitando o cadastro do cliente.', icon: Handshake },
    { name: 'Organizar', text: 'Serviços, profissionais e horários reunidos na mesma operação.', icon: ClipboardList },
    { name: 'Fidelizar', text: 'Histórico do cliente sempre à mão para o próximo atendimento.', icon: Heart },
    { name: 'Crescer', text: 'Indicadores para decidir onde colocar o tempo e o esforço da equipe.', icon: TrendingUp },
];

/**
 * The journey as a numbered sequence. Desktop shows the six stages on one line; phones stack them.
 * The copy describes what each stage is for, not a feature that is not yet delivered.
 */
export function JourneySection() {
    return (
        <SectionShell
            id="plataforma"
            tone="surface"
            eyebrow="Jornada de crescimento"
            title="Do primeiro contato ao crescimento do seu negócio."
            lead="O VetorSmart não é só um sistema administrativo. Ele acompanha a jornada inteira do cliente, usando um único cadastro em todas as etapas."
        >
            <ol className="relative grid gap-8 sm:grid-cols-2 lg:grid-cols-6 lg:gap-5">
                <span aria-hidden="true" className="absolute left-[8%] right-[8%] top-6 hidden h-px bg-vs-line lg:block" />
                {steps.map((step, index) => {
                    const Icon = step.icon;

                    return (
                        <li key={step.name} className="relative flex flex-col gap-4">
                            <span className="relative z-10 flex size-12 items-center justify-center rounded-full border border-vs-line bg-white text-vs-ink-blue shadow-sm">
                                <Icon aria-hidden="true" className="size-5" strokeWidth={1.75} />
                            </span>
                            <div>
                                <p className="text-xs font-semibold tracking-[0.14em] text-vs-muted">
                                    <span className="tabular-nums">{String(index + 1).padStart(2, '0')}</span>
                                </p>
                                <h3 className="mt-1 text-lg font-semibold text-vs-text">{step.name}</h3>
                                <p className="mt-2 text-sm leading-relaxed text-vs-muted">{step.text}</p>
                            </div>
                        </li>
                    );
                })}
            </ol>
        </SectionShell>
    );
}
