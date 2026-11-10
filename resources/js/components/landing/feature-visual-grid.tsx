import { Bot, CalendarDays, ClipboardList, Globe, Smartphone, TrendingUp, type LucideIcon } from 'lucide-react';
import { StatusBadge, type CapabilityStatus } from './section-shell';

type FeatureItem = { label: string; status: CapabilityStatus };

type Feature = {
    title: string;
    description: string;
    icon: LucideIcon;
    /** Soft tint of the card and the matching ink for its icon. Each tint identifies one capability area. */
    tint: string;
    ink: string;
    items: FeatureItem[];
};

/** One card per capability area. Every item carries its real status. */
const features: Feature[] = [
    {
        title: 'Site profissional e SEO',
        description: 'Presença digital com endereço próprio e otimização para buscas.',
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
        title: 'Atendimento inteligente',
        description: 'Recepcionista virtual que responde com base nos serviços e horários cadastrados pela empresa.',
        icon: Bot,
        tint: 'bg-vs-tint-violet',
        ink: 'text-vs-ink-violet',
        items: [
            { label: 'Recepcionista virtual', status: 'development' },
            { label: 'Qualificação de contatos', status: 'development' },
        ],
    },
    {
        title: 'Agenda e agendamentos',
        description: 'Horários, profissionais e reservas organizados em um só lugar.',
        icon: CalendarDays,
        tint: 'bg-vs-tint-cyan',
        ink: 'text-vs-ink-cyan',
        items: [
            { label: 'Disponibilidade de profissionais', status: 'available' },
            { label: 'Reserva de horário', status: 'development' },
        ],
    },
    {
        title: 'Gestão de serviços',
        description: 'Clientes, serviços, profissionais e a operação do dia a dia em um painel.',
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
        title: 'Meu Crescimento',
        description: 'Indicadores e oportunidades para acompanhar os resultados da sua empresa.',
        icon: TrendingUp,
        tint: 'bg-vs-tint-green',
        ink: 'text-vs-ink-green',
        items: [
            { label: 'Indicadores de resultado', status: 'development' },
            { label: 'Pedido de avaliação no Google', status: 'development' },
        ],
    },
    {
        title: 'Aplicativo VetorSmart',
        description: 'Acesso à operação pelo celular, com a versão web já responsiva.',
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
                    <article key={feature.title} className={`flex flex-col gap-6 rounded-2xl border border-vs-line p-7 transition-shadow duration-200 hover:shadow-md motion-reduce:transition-none sm:p-8 ${feature.tint}`}>
                        <span className={`flex size-12 items-center justify-center rounded-xl bg-white/70 ${feature.ink}`}>
                            <Icon aria-hidden="true" className="size-6" strokeWidth={1.75} />
                        </span>

                        <div>
                            <h3 className="text-xl font-semibold tracking-tight text-vs-text">{feature.title}</h3>
                            <p className="mt-2 text-sm leading-relaxed text-vs-text/75">{feature.description}</p>
                        </div>

                        <ul className="mt-auto grid gap-3 border-t border-vs-text/10 pt-5">
                            {feature.items.map((item) => (
                                <li key={item.label} className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5 text-sm text-vs-text">
                                    <span>{item.label}</span>
                                    <StatusBadge status={item.status} />
                                </li>
                            ))}
                        </ul>
                    </article>
                );
            })}
        </div>
    );
}
