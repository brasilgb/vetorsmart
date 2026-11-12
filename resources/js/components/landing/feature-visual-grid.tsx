import { Bot, CalendarDays, ClipboardList, Globe, Smartphone, TrendingUp, type LucideIcon } from 'lucide-react';
import { landingCard } from './landing-layout';
import { StatusBadge, type CapabilityStatus } from './section-shell';

type FeatureItem = { label: string; status: CapabilityStatus };

type Feature = {
    title: string;
    tag: string;
    description: string;
    icon: LucideIcon;
    /** Soft tint for the icon tile and tag, with the matching ink. Each tint identifies one capability area. */
    tint: string;
    ink: string;
    items: FeatureItem[];
};

/** One card per capability area. Every item carries its real status. */
const features: Feature[] = [
    {
        title: 'Site profissional para seu negócio',
        tag: 'Presença digital',
        description: 'Presença digital própria, com endereço próprio e otimizada para buscas no Google.',
        icon: Globe,
        tint: 'bg-vs-tint-blue',
        ink: 'text-vs-ink-blue',
        items: [
            { label: 'Meu Site', status: 'available' },
            { label: 'Domínio próprio', status: 'available' },
            { label: 'Metadados e sitemap', status: 'available' },
        ],
    },
    {
        title: 'Atendimento inteligente com IA',
        tag: 'Automação',
        description: 'Recepcionista virtual que responde com base nos serviços e horários cadastrados pela empresa.',
        icon: Bot,
        tint: 'bg-vs-tint-cyan',
        ink: 'text-vs-ink-cyan',
        items: [
            { label: 'Recepcionista virtual', status: 'development' },
            { label: 'Qualificação de contatos', status: 'development' },
        ],
    },
    {
        title: 'Agenda organizada',
        tag: 'Sem conflitos',
        description: 'Datas, horários e profissionais disponíveis em um só lugar, sem desencontros na equipe.',
        icon: CalendarDays,
        tint: 'bg-vs-tint-green',
        ink: 'text-vs-ink-green',
        items: [
            { label: 'Disponibilidade de profissionais', status: 'available' },
            { label: 'Reserva de horário', status: 'development' },
        ],
    },
    {
        title: 'Gestão de serviços e clientes',
        tag: 'Operação',
        description: 'Clientes, serviços, profissionais e a operação do dia a dia reunidos em um painel.',
        icon: ClipboardList,
        tint: 'bg-vs-tint-orange',
        ink: 'text-vs-ink-orange',
        items: [
            { label: 'Clientes', status: 'available' },
            { label: 'Serviços', status: 'available' },
            { label: 'Profissionais', status: 'available' },
        ],
    },
    {
        title: 'Indicadores de crescimento',
        tag: 'Visão estratégica',
        description: 'Indicadores e oportunidades para acompanhar os resultados da sua empresa com clareza.',
        icon: TrendingUp,
        tint: 'bg-vs-tint-violet',
        ink: 'text-vs-ink-violet',
        items: [
            { label: 'Indicadores de resultado', status: 'development' },
            { label: 'Pedido de avaliação no Google', status: 'development' },
        ],
    },
    {
        title: 'Acesso pelo celular',
        tag: 'Mobilidade',
        description: 'A versão web já funciona no celular, no tablet e no computador.',
        icon: Smartphone,
        tint: 'bg-vs-tint-slate',
        ink: 'text-vs-ink-slate',
        items: [
            { label: 'Versão web responsiva', status: 'available' },
            { label: 'Aplicativo e PWA', status: 'soon' },
        ],
    },
];

/** Six capability cards in a three-column grid on desktop, two on tablet and one on phones. */
export function FeatureVisualGrid() {
    return (
        <div className="grid items-stretch gap-6 md:grid-cols-2 lg:grid-cols-3">
            {features.map((feature) => {
                const Icon = feature.icon;

                return (
                    <article key={feature.title} className={`${landingCard} flex flex-col p-8 transition-shadow duration-200 hover:shadow-md motion-reduce:transition-none`}>
                        <span className={`flex size-12 items-center justify-center rounded-xl ${feature.tint} ${feature.ink}`}>
                            <Icon aria-hidden="true" className="size-6" strokeWidth={1.75} />
                        </span>

                        <span className={`mt-4 w-fit rounded-full px-2.5 py-0.5 text-[0.6875rem] font-semibold tracking-wide ${feature.tint} ${feature.ink}`}>{feature.tag}</span>
                        <h3 className="vs-title-card mt-2 text-vs-text">{feature.title}</h3>
                        <p className="mt-2 text-base leading-relaxed text-vs-muted">{feature.description}</p>

                        <div className="mt-auto pt-5">
                            <ul className="grid gap-2.5 border-t border-slate-100 pt-4">
                                {feature.items.map((item) => (
                                    <li key={item.label} className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5 text-sm text-vs-text">
                                        <span>{item.label}</span>
                                        <StatusBadge status={item.status} />
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </article>
                );
            })}
        </div>
    );
}
