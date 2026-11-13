import { Link } from '@inertiajs/react';
import { ArrowRight, CircleCheck, Clock, type LucideIcon } from 'lucide-react';
import { register } from '@/routes';
import { landingButtonPrimary } from './landing-layout';
import type { CapabilityStatus } from './section-shell';

type PlanItem = { label: string; status: CapabilityStatus };

/** Single plan from the Stitch screen. The price is a hypothesis to validate, never an approved value. */
const plan = {
    name: 'VetorSmart Completo',
    summary: 'Tudo o que sua prestadora de serviços precisa para crescer de verdade.',
    price: '99,90',
    items: [
        { label: 'Site profissional com seu domínio', status: 'available' },
        { label: 'Clientes, serviços e profissionais', status: 'available' },
        { label: 'Agenda com reserva de horário', status: 'development' },
        { label: 'Assistente de IA para atendimento 24/7', status: 'development' },
        { label: 'Controle de ordens de serviço (OS) e solicitações', status: 'development' },
        { label: 'Acesso responsivo no celular, tablet e computador', status: 'available' },
        { label: 'Aplicativo e PWA', status: 'soon' },
    ] satisfies PlanItem[],
};

const itemIcon: Record<CapabilityStatus, { icon: LucideIcon; className: string; label: string }> = {
    available: { icon: CircleCheck, className: 'text-emerald-600', label: 'Disponível' },
    development: { icon: Clock, className: 'text-vs-ink-orange', label: 'Em desenvolvimento' },
    soon: { icon: Clock, className: 'text-vs-ink-slate', label: 'Em breve' },
};

/**
 * Single-plan card from the Stitch "Preço simples e transparente" section. Each feature shows its real
 * status as text, so availability is never conveyed by colour alone.
 */
export function PricingSection() {
    return (
        <div className="mx-auto max-w-xl">
            <article className="relative rounded-2xl border-2 border-vs-blue-hover/20 bg-white p-8 shadow-xl sm:p-10">
                <span className="absolute -top-3.5 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-vs-blue-hover px-4 py-1 text-xs font-semibold text-white shadow-sm">
                    Plano completo • Acesso total
                </span>

                <div className="border-b border-slate-100 pb-6 text-center">
                    <h3 className="font-display text-xl font-bold text-vs-text">{plan.name}</h3>
                    <p className="mt-1 text-sm leading-relaxed text-vs-muted">{plan.summary}</p>
                    <p className="mt-5 flex items-baseline justify-center gap-1.5 text-vs-text">
                        <span className="font-display text-2xl font-bold">R$</span>
                        <span className="font-display text-5xl font-extrabold tracking-tight">{plan.price}</span>
                        <span className="text-sm font-medium text-vs-muted">/mês</span>
                    </p>
                    <p className="mt-2 text-xs text-vs-muted">Valor em validação. Sem fidelidade obrigatória.</p>
                </div>

                <ul className="grid gap-3.5 py-6 text-sm">
                    {plan.items.map((item) => {
                        const { icon: Icon, className, label } = itemIcon[item.status];

                        return (
                            <li key={item.label} className="flex items-start gap-3">
                                <Icon aria-hidden="true" className={`mt-0.5 size-4 shrink-0 ${className}`} />
                                <span className="text-vs-text">
                                    {item.label}
                                    {item.status !== 'available' && <span className="mt-0.5 block text-xs text-vs-muted">{label}</span>}
                                </span>
                            </li>
                        );
                    })}
                </ul>

                <Link href={register()} className={`w-full ${landingButtonPrimary}`}>
                    Começar agora
                    <ArrowRight aria-hidden="true" className="size-4" />
                </Link>
                <p className="mt-3 text-center text-xs text-vs-muted">Não há cobrança ativa nesta versão. Condições como período de teste ainda serão definidas.</p>
            </article>
        </div>
    );
}
