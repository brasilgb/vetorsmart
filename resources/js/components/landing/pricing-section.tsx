import { Link } from '@inertiajs/react';
import { ArrowRight, CircleCheck, Clock, type LucideIcon } from 'lucide-react';
import { register } from '@/routes';
import { landingButtonPrimary, landingButtonSecondary } from './landing-layout';
import type { CapabilityStatus } from './section-shell';

type PlanItem = { label: string; status: CapabilityStatus };

type Plan = {
    name: string;
    price: string;
    summary: string;
    featured?: boolean;
    items: PlanItem[];
};

/** Values are hypotheses to validate, never approved prices. Each item states its real status. */
const plans: Plan[] = [
    {
        name: 'Essencial',
        price: 'R$ 99,90',
        summary: 'Para começar com site, gestão e operação organizada.',
        items: [
            { label: 'Meu Site com SEO', status: 'available' },
            { label: 'Clientes, serviços e profissionais', status: 'available' },
            { label: 'Até 2 usuários', status: 'available' },
            { label: 'Agenda com reserva de horário', status: 'development' },
            { label: 'Aplicativo e PWA', status: 'soon' },
        ],
    },
    {
        name: 'Profissional',
        price: 'R$ 199,90',
        summary: 'Para empresas que querem presença própria e mais controle da equipe.',
        featured: true,
        items: [
            { label: 'Tudo do Essencial', status: 'available' },
            { label: 'Domínio próprio', status: 'available' },
            { label: 'Até 5 usuários', status: 'available' },
            { label: 'Recepcionista virtual com franquia', status: 'development' },
            { label: 'Meu Crescimento', status: 'development' },
        ],
    },
    {
        name: 'Premium',
        price: 'R$ 349,90',
        summary: 'Para operações maiores, com mais usuários e automações.',
        items: [
            { label: 'Tudo do Profissional', status: 'available' },
            { label: 'Até 15 usuários', status: 'available' },
            { label: 'Franquia ampliada de atendimento', status: 'development' },
            { label: 'Automações avançadas', status: 'development' },
            { label: 'Relatórios de crescimento', status: 'development' },
        ],
    },
];

const itemIcon: Record<CapabilityStatus, { icon: LucideIcon; className: string; label: string }> = {
    available: { icon: CircleCheck, className: 'text-emerald-600', label: 'Disponível' },
    development: { icon: Clock, className: 'text-vs-ink-orange', label: 'Em desenvolvimento' },
    soon: { icon: Clock, className: 'text-vs-ink-slate', label: 'Em breve' },
};

/**
 * Three plans. Professional is the recommended plan and is visually primary. Each feature shows an
 * icon and a text label, so availability is never conveyed by colour alone.
 */
export function PricingSection() {
    return (
        <>
            <div className="grid items-stretch gap-6 lg:grid-cols-3">
                {plans.map((plan) => (
                    <article
                        key={plan.name}
                        className={`relative flex flex-col rounded-2xl bg-white p-8 ${plan.featured ? 'border-2 border-vs-blue-hover/30 shadow-xl lg:-my-4' : 'border border-vs-line shadow-sm'}`}
                    >
                        {plan.featured && (
                            <span className="absolute -top-3.5 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-vs-blue-hover px-4 py-1 text-xs font-semibold text-white shadow-sm">
                                Recomendado
                            </span>
                        )}

                        <div className="border-b border-slate-100 pb-6 text-center">
                            <h3 className="font-display text-xl font-bold text-vs-text">{plan.name}</h3>
                            <p className="mt-1 min-h-[2.75rem] text-sm leading-relaxed text-vs-muted">{plan.summary}</p>
                            <p className="mt-4 flex items-baseline justify-center gap-1.5 text-vs-text">
                                <span className="font-display text-5xl font-extrabold tracking-tight">{plan.price}</span>
                                <span className="text-sm font-medium text-vs-muted">/mês</span>
                            </p>
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

                        <Link href={register()} className={`mt-auto w-full ${plan.featured ? landingButtonPrimary : landingButtonSecondary}`}>
                            Começar agora
                            <ArrowRight aria-hidden="true" className="size-4" />
                        </Link>
                    </article>
                ))}
            </div>
            <p className="mx-auto mt-12 max-w-3xl text-center text-sm leading-relaxed text-vs-muted">
                Os valores são hipóteses a validar, não preços aprovados. Não há cobrança ativa nesta versão, e condições como o período de teste ainda serão definidas.
            </p>
        </>
    );
}
