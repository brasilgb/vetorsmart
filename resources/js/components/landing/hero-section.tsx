import { Link } from '@inertiajs/react';
import { ArrowRight, Bell, Calendar, CheckCircle2, CirclePlay, Sparkles } from 'lucide-react';
import { register } from '@/routes';
import { landingButtonPrimary, landingButtonSecondary, landingContainer } from './landing-layout';

const promises = ['Ser encontrado', 'Atender melhor', 'Crescer mais'];

const kpis = [
    { label: 'Oportunidades', value: '28', delta: '+18%', tone: 'text-vs-text', hint: 'Este mês' },
    { label: 'Em andamento', value: '12', delta: 'ordens', tone: 'text-vs-blue-hover', hint: '4 equipes' },
    { label: 'Agendados hoje', value: '6', delta: '100% ok', tone: 'text-vs-text', hint: 'Rotas otimizadas' },
];

const agenda = [
    { time: '09:00', title: 'Instalação Split Inverter 12k • Ed. Mirage', meta: 'Técnico: Lucas Rodrigues • Rua das Palmeiras, 450', status: 'Em atendimento', tone: 'bg-blue-100 text-vs-blue-hover' },
    { time: '11:30', title: 'Manutenção Preventiva Comercial • Alfa Corp', meta: 'Técnico: Gabriel Santos • Av. Paulista, 1200', status: 'Confirmado', tone: 'bg-emerald-100 text-emerald-800' },
    { time: '14:00', title: 'Higienização e carga de gás • Res. Flamboyant', meta: 'Técnico: Lucas Rodrigues • Bloco B, Apt 602', status: 'Confirmado', tone: 'bg-emerald-100 text-emerald-800' },
];

/**
 * Hero from the Stitch "Landing Page Oficial (Desktop)": centred copy, CTAs and proof points, then the
 * product mockup with KPIs, agenda and the AI receptionist chat. The mockup is illustrative: names and
 * figures are sample data, not customers.
 */
export function HeroSection() {
    return (
        <section id="inicio" aria-labelledby="hero-title" className="relative overflow-hidden bg-gradient-to-b from-vs-canvas-blue/40 via-vs-canvas to-vs-canvas">
            <div aria-hidden="true" className="pointer-events-none absolute -top-32 left-1/2 h-[340px] w-[760px] -translate-x-1/2 rounded-full bg-gradient-to-tr from-vs-blue/10 to-vs-cyan/15 blur-3xl" />
            <div aria-hidden="true" className="pointer-events-none absolute right-[-6rem] top-1/3 size-96 rounded-full bg-vs-blue/5 blur-3xl" />

            <div className={`${landingContainer} relative pb-20 pt-14 lg:pb-28 lg:pt-20`}>
                <div className="vs-rise mx-auto flex max-w-4xl flex-col items-center gap-3 text-center">
                    <p className="inline-flex flex-wrap items-center justify-center gap-2 rounded-full border border-vs-line bg-white px-3.5 py-1.5 text-xs font-medium shadow-sm">
                        <span aria-hidden="true" className="inline-flex size-2 shrink-0 rounded-full bg-emerald-500 motion-safe:animate-pulse" />
                        <span className="text-slate-600">A plataforma inteligente para quem presta serviços</span>
                        <span aria-hidden="true" className="hidden text-slate-300 sm:inline">•</span>
                        <span className="font-semibold text-vs-blue-hover">Venha crescer com a gente</span>
                    </p>

                    <h1 id="hero-title" className="vs-title-hero mt-4 text-balance text-vs-text">
                        Seu negócio mais organizado.{' '}
                        <span className="text-vs-blue-hover">Seus clientes mais próximos.</span>
                    </h1>

                    <p className="mt-4 max-w-2xl text-base leading-relaxed text-vs-muted sm:text-lg">
                        Tenha um site profissional, atendimento com inteligência artificial, agenda e gestão de serviços em uma única plataforma. Simplifique sua rotina e tenha mais oportunidades para crescer.
                    </p>

                    <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-center sm:gap-4">
                        <Link href={register()} className={landingButtonPrimary}>
                            Começar agora
                            <ArrowRight aria-hidden="true" className="size-4" />
                        </Link>
                        <a href="#funcionalidades" className={landingButtonSecondary}>
                            <CirclePlay aria-hidden="true" className="size-5 text-vs-blue-hover" />
                            Conhecer a plataforma
                        </a>
                    </div>

                    <ul className="mt-6 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs font-semibold text-vs-muted">
                        {promises.map((promise) => (
                            <li key={promise} className="inline-flex items-center gap-1.5">
                                <CheckCircle2 aria-hidden="true" className="size-4 text-emerald-500" />
                                {promise}
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="vs-rise relative mt-14 lg:mt-16">
                    <div className="relative rounded-2xl border border-vs-line bg-white p-3 shadow-xl sm:p-4">
                        <div className="mb-3 flex items-center justify-between px-1 pb-3">
                            <div className="flex items-center gap-2">
                                <span aria-hidden="true" className="size-3 rounded-full bg-red-400" />
                                <span aria-hidden="true" className="size-3 rounded-full bg-amber-400" />
                                <span aria-hidden="true" className="size-3 rounded-full bg-emerald-400" />
                                <span className="ml-2 text-xs font-medium text-vs-muted">vetorsmart.app/painel — ClimaTech Refrigeração &amp; Elétrica</span>
                            </div>
                            <div className="flex items-center gap-3">
                                <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                    <span aria-hidden="true" className="size-1.5 rounded-full bg-emerald-500" />
                                    Online
                                </span>
                                <Bell aria-hidden="true" className="size-4 text-vs-muted" />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 rounded-xl bg-vs-canvas-blue p-4 lg:grid-cols-12">
                            <div className="flex flex-col gap-4 lg:col-span-7">
                                <div className="grid grid-cols-3 gap-3">
                                    {kpis.map((kpi) => (
                                        <div key={kpi.label} className="rounded-xl border border-vs-line bg-white p-4 shadow-sm">
                                            <span className="text-[11px] font-semibold uppercase text-vs-muted">{kpi.label}</span>
                                            <div className="mt-1 flex items-baseline gap-2">
                                                <span className={`font-display text-2xl font-bold ${kpi.tone}`}>{kpi.value}</span>
                                                <span className="text-xs font-semibold text-emerald-600">{kpi.delta}</span>
                                            </div>
                                            <span className="mt-1 block text-xs text-vs-muted">{kpi.hint}</span>
                                        </div>
                                    ))}
                                </div>

                                <div className="rounded-xl border border-vs-line bg-white p-4 shadow-sm">
                                    <div className="mb-3 flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Calendar aria-hidden="true" className="size-5 text-vs-blue-hover" />
                                            <h3 className="text-sm font-semibold text-vs-text">Agenda de serviços do dia</h3>
                                        </div>
                                        <span className="text-xs font-semibold text-vs-blue-hover">Ver semana</span>
                                    </div>
                                    <ul className="grid gap-2">
                                        {agenda.map((item) => (
                                            <li key={item.time} className="flex items-center justify-between gap-3 rounded-lg bg-vs-canvas p-2.5">
                                                <div className="flex min-w-0 items-center gap-3">
                                                    <span className="rounded bg-white px-2 py-1 text-xs font-bold text-vs-text shadow-xs">{item.time}</span>
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-semibold text-vs-text">{item.title}</p>
                                                        <p className="truncate text-xs text-vs-muted">{item.meta}</p>
                                                    </div>
                                                </div>
                                                <span className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ${item.tone}`}>{item.status}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>

                                <div className="flex items-center justify-between rounded-xl border border-vs-line bg-white p-4 shadow-sm">
                                    <div>
                                        <span className="text-[11px] font-semibold uppercase text-vs-muted">Evolução de atendimentos mensais</span>
                                        <p className="mt-0.5 text-sm font-bold text-vs-text">84 atendimentos concluídos</p>
                                    </div>
                                    <svg aria-hidden="true" viewBox="0 0 160 36" className="h-10 w-48 fill-none stroke-vs-blue-hover" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                        <path d="M0,28 Q20,24 40,26 T80,18 T120,12 T160,4" />
                                        <circle cx="160" cy="4" r="3.5" className="fill-vs-blue-hover stroke-white" strokeWidth="1.5" />
                                    </svg>
                                </div>
                            </div>

                            <div className="flex flex-col justify-between rounded-xl border border-vs-line bg-white p-4 shadow-sm lg:col-span-5">
                                <div>
                                    <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                                        <div className="flex items-center gap-2">
                                            <span className="flex size-8 items-center justify-center rounded-full bg-gradient-to-tr from-vs-blue-hover to-vs-cyan text-white">
                                                <Sparkles aria-hidden="true" className="size-4" />
                                            </span>
                                            <div>
                                                <h4 className="text-sm font-bold text-vs-text">VetorSmart AI</h4>
                                                <p className="text-xs font-medium text-emerald-600">Recepcionista virtual ativa • 24/7</p>
                                            </div>
                                        </div>
                                        <span className="rounded bg-vs-canvas-blue px-2 py-0.5 text-xs font-semibold text-vs-muted">Site ClimaTech</span>
                                    </div>

                                    <div className="mt-3 flex flex-col gap-2.5 text-xs">
                                        <p className="max-w-[85%] self-end rounded-xl rounded-tr-none bg-vs-blue-hover px-3 py-2 text-white">
                                            Olá! Preciso de manutenção preventiva em 2 aparelhos de ar-condicionado na minha empresa.
                                        </p>
                                        <p className="max-w-[88%] self-start rounded-xl rounded-tl-none bg-vs-canvas-blue px-3 py-2 leading-relaxed text-vs-text">
                                            Olá! Perfeito. Qual a marca e capacidade dos aparelhos, e qual o melhor período para nossa equipe técnica fazer a visita?
                                        </p>
                                        <p className="max-w-[85%] self-end rounded-xl rounded-tr-none bg-vs-blue-hover px-3 py-2 text-white">
                                            São 2 Daikin de 12.000 BTUs. Preferência na quinta-feira pela manhã.
                                        </p>
                                        <p className="max-w-[88%] self-start rounded-xl rounded-tl-none bg-vs-canvas-blue px-3 py-2 leading-relaxed text-vs-text">
                                            Anotado! Já pré-agendei na quinta às 09:30 com o técnico Lucas. Confirmando seu WhatsApp para envio do link da OS?
                                        </p>
                                    </div>
                                </div>

                                <div className="mt-4 flex items-center justify-between rounded-lg border border-emerald-100 bg-emerald-50 p-2.5">
                                    <div className="flex items-center gap-2">
                                        <CheckCircle2 aria-hidden="true" className="size-5 text-emerald-600" />
                                        <div>
                                            <span className="block text-xs font-bold text-emerald-900">Novo lead qualificado</span>
                                            <span className="block text-[11px] text-emerald-700">Adicionado à agenda automaticamente</span>
                                        </div>
                                    </div>
                                    <span className="rounded bg-emerald-600 px-2 py-0.5 text-xs font-semibold text-white">OS #1042</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
