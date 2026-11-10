import { Link } from '@inertiajs/react';
import { Check, Clock, type LucideIcon } from 'lucide-react';
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
    available: { icon: Check, className: 'text-vs-ink-green', label: 'Disponível' },
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
                        className={`relative flex flex-col gap-6 rounded-2xl bg-white p-7 sm:p-8 ${plan.featured ? 'border-2 border-vs-blue shadow-xl lg:-my-4' : 'border border-vs-line shadow-sm'}`}
                    >
                        {plan.featured && (
                            <span className="absolute -top-3 left-8 rounded-full bg-vs-blue px-3 py-1 text-xs font-semibold text-white">Recomendado</span>
                        )}

                        <div>
                            <h3 className="text-xl font-semibold text-vs-text">{plan.name}</h3>
                            <p className="mt-2 min-h-[3rem] text-sm leading-relaxed text-vs-muted">{plan.summary}</p>
                        </div>

                        <p className="flex items-baseline gap-1.5 text-vs-text">
                            <span className="text-4xl font-semibold tracking-tight">{plan.price}</span>
                            <span className="text-sm text-vs-muted">/mês</span>
                        </p>

                        <Link href={register()} className={plan.featured ? landingButtonPrimary : landingButtonSecondary}>
                            Começar
                        </Link>

                        <ul className="grid gap-3 border-t border-vs-line pt-6 text-sm">
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
                    </article>
                ))}
            </div>
            <p className="mt-10 max-w-3xl text-sm leading-relaxed text-vs-muted">
                Os valores são hipóteses a validar, não preços aprovados. Não há cobrança ativa nesta versão, e condições como o período de teste ainda serão definidas.
            </p>
        </>
    );
}
