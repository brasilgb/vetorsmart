import { Link } from '@inertiajs/react';
import { ArrowRight, CircleCheck, CirclePlay } from 'lucide-react';
import { register } from '@/routes';
import { landingButtonOnDark, landingButtonPrimary, landingContainer } from './landing-layout';

/**
 * Service photograph (plumber at work) under a navy film for contrast. Its licence is not yet
 * recorded in docs/VETORSMART-IMAGE-CREDITS.md; verify and register it before publishing.
 */
const heroBackground = '/images/landing/hero-plumber.webp';

const promises = ['Ser encontrado', 'Atender melhor', 'Crescer mais'];

/**
 * Hero: copy, badges and calls to action from the Stitch landing, over the service photograph. The
 * user asked to keep the hero imagery, so the Stitch dashboard mockup is not used here.
 */
export function HeroSection() {
    return (
        <section id="inicio" aria-labelledby="hero-title" className="relative overflow-hidden bg-vs-ink text-white">
            <div aria-hidden="true" className="absolute inset-0 bg-cover bg-center opacity-75" style={{ backgroundImage: `url('${heroBackground}')` }} />
            <div aria-hidden="true" className="absolute inset-0 bg-gradient-to-r from-vs-ink via-vs-ink/85 to-vs-ink/40" />
            <div aria-hidden="true" className="absolute inset-0 bg-[radial-gradient(60rem_30rem_at_85%_-10%,rgba(37,99,235,0.28),transparent)]" />

            <div className={`${landingContainer} relative grid items-center gap-14 pb-20 pt-14 lg:grid-cols-[1.05fr_0.95fr] lg:gap-16 lg:pb-28 lg:pt-20`}>
                <div className="vs-rise">
                    <p className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3.5 py-1.5 text-xs font-semibold backdrop-blur">
                        <span aria-hidden="true" className="inline-flex size-2 shrink-0 rounded-full bg-emerald-400 motion-safe:animate-pulse" />
                        <span className="text-slate-200">A plataforma inteligente para quem presta serviços</span>
                        <span aria-hidden="true" className="hidden text-slate-500 lg:inline">•</span>
                        <span className="hidden text-vs-cyan lg:inline">Venha crescer com a gente</span>
                    </p>

                    <h1 id="hero-title" className="vs-title-hero mt-6 text-balance text-white">
                        Seu negócio mais organizado.{' '}
                        <span className="text-sky-300">Seus clientes mais próximos.</span>
                    </h1>

                    <p className="mt-6 max-w-xl text-base leading-relaxed text-slate-300 sm:text-lg">
                        Tenha um site profissional, agenda e gestão de serviços em uma única plataforma, com atendimento por inteligência artificial a caminho. Simplifique sua rotina e tenha mais oportunidades para crescer.
                    </p>

                    <div className="mt-9 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:gap-4">
                        <Link href={register()} className={landingButtonPrimary}>
                            Começar agora
                            <ArrowRight aria-hidden="true" className="size-4" />
                        </Link>
                        <a href="#funcionalidades" className={landingButtonOnDark}>
                            <CirclePlay aria-hidden="true" className="size-5 text-sky-300" />
                            Conhecer a plataforma
                        </a>
                    </div>

                    <ul className="mt-8 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs font-semibold text-slate-300">
                        {promises.map((promise) => (
                            <li key={promise} className="inline-flex items-center gap-1.5">
                                <CircleCheck aria-hidden="true" className="size-4 text-emerald-400" />
                                {promise}
                            </li>
                        ))}
                    </ul>
                </div>

                <figure className="vs-rise relative mx-auto w-full max-w-xl overflow-hidden rounded-3xl border border-white/10 shadow-2xl">
                    <img
                        src="/images/workshop.png"
                        alt="Técnico de uniforme azul-marinho consultando um tablet em uma oficina de serviços"
                        width="1672"
                        height="941"
                        className="aspect-[4/3] w-full object-cover"
                    />
                    <figcaption className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-vs-ink/90 to-transparent px-6 pb-5 pt-16 font-display text-lg font-semibold text-white sm:text-xl">
                        Tenha tudo em sua mão
                    </figcaption>
                </figure>
            </div>
        </section>
    );
}
