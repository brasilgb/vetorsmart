import { Bot, CalendarDays, ClipboardList, Globe, Smartphone, TrendingUp, type LucideIcon } from 'lucide-react';
import { landingCard } from './landing-layout';
import { type CapabilityStatus, StatusBadge } from './section-shell';

type FeatureItem = { label: string; status: CapabilityStatus };

type Feature = {
    title: string;
    text: string;
    area: string;
    icon: LucideIcon;
    tile: string;
    items: FeatureItem[];
};

/** Each card carries the real status of every item, so nothing reads as delivered when it is not. */
const features: Feature[] = [
    {
        title: 'Site profissional para seu negócio',
        text: 'Presença digital própria, rápida e otimizada para o Google, com seu domínio e pronta para converter visitantes em clientes.',
        area: 'Presença digital',
        icon: Globe,
        tile: 'bg-blue-50 text-vs-blue-hover',
        items: [
            { label: 'Meu Site', status: 'available' },
            { label: 'Domínio próprio', status: 'available' },
            { label: 'Metadados e sitemap', status: 'available' },
        ],
    },
    {
        title: 'Atendimento inteligente com IA',
        text: 'Recepcionista virtual que responde dúvidas, coleta as necessidades essenciais e organiza o primeiro contato a qualquer hora.',
        area: 'Automação 24/7',
        icon: Bot,
        tile: 'bg-cyan-50 text-cyan-700',
        items: [
            { label: 'Recepcionista virtual', status: 'development' },
            { label: 'Qualificação de contatos', status: 'development' },
        ],
    },
    {
        title: 'Agenda organizada',
        text: 'Gestão clara de datas, horários e profissionais disponíveis, para acabar com desencontros, furos e retrabalho na equipe.',
        area: 'Zero conflitos',
        icon: CalendarDays,
        tile: 'bg-emerald-50 text-emerald-700',
        items: [
            { label: 'Disponibilidade de profissionais', status: 'available' },
            { label: 'Reserva de horário', status: 'development' },
        ],
    },
    {
        title: 'Gestão de serviços e clientes',
        text: 'Acompanhe cada ordem de serviço e mantenha a base de clientes centralizada, com o histórico de atendimentos para pós-venda e recompra.',
        area: 'Operação',
        icon: ClipboardList,
        tile: 'bg-amber-50 text-amber-700',
        items: [
            { label: 'Clientes', status: 'available' },
            { label: 'Serviços', status: 'available' },
            { label: 'Profissionais', status: 'available' },
        ],
    },
    {
        title: 'Indicadores de crescimento',
        text: 'Visão transparente de contatos recebidos, taxa de conversão e serviços concluídos para decidir com segurança.',
        area: 'Visão estratégica',
        icon: TrendingUp,
        tile: 'bg-purple-50 text-purple-700',
        items: [
            { label: 'Indicadores de resultado', status: 'development' },
            { label: 'Pedido de avaliação no Google', status: 'development' },
        ],
    },
    {
        title: 'Acesso pelo celular',
        text: 'Abra a plataforma no navegador do celular, do tablet ou do computador, sem instalar nada.',
        area: 'Mobile first',
        icon: Smartphone,
        tile: 'bg-indigo-50 text-indigo-700',
        items: [
            { label: 'Versão web responsiva', status: 'available' },
            { label: 'Aplicativo e PWA', status: 'soon' },
        ],
    },
];

/** Six Stitch cards, one per capability area. The grid is the body of the "Funcionalidades" section. */
export function FeatureVisualGrid() {
    return (
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            {features.map((feature) => {
                const Icon = feature.icon;

                return (
                    <article key={feature.title} className={`${landingCard} flex flex-col p-7 transition-shadow duration-200 hover:shadow-md motion-reduce:transition-none`}>
                        <div className="flex items-center justify-between">
                            <span aria-hidden="true" className={`flex size-12 items-center justify-center rounded-xl ${feature.tile}`}>
                                <Icon className="size-6" />
                            </span>
                            <span className="rounded-full bg-vs-canvas-blue px-2.5 py-0.5 text-xs font-semibold text-vs-blue-hover">{feature.area}</span>
                        </div>
                        <h3 className="vs-title-card mt-5 text-vs-text">{feature.title}</h3>
                        <p className="mt-2 text-sm leading-relaxed text-vs-muted">{feature.text}</p>

                        <ul className="mt-5 grid gap-2.5 border-t border-vs-line pt-4">
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
