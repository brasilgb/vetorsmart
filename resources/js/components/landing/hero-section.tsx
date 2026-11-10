import { Link } from '@inertiajs/react';
import { register } from '@/routes';
import { landingButtonOnDark, landingButtonPrimary, landingContainer } from './landing-layout';
import { ElectricianAppIllustration } from './electrician-app-illustration';

/**
 * Service photograph (plumber at work) under a navy film for contrast. Its licence is not yet
 * recorded in docs/VETORSMART-IMAGE-CREDITS.md; verify and register it before publishing.
 */
const heroBackground = '/images/landing/hero-plumber.webp';

/**
 * Hero: message and calls to action over the service photograph. The product preview was removed
 * at the user's request, so the hero carries no dashboard image.
 */
export function HeroSection() {
    return (
        <section id="inicio" aria-labelledby="hero-title" className="relative overflow-hidden bg-vs-ink text-white">
            <div aria-hidden="true" className="absolute inset-0 bg-cover bg-center opacity-75" style={{ backgroundImage: `url('${heroBackground}')` }} />
            <div aria-hidden="true" className="absolute inset-0 bg-gradient-to-r from-vs-ink via-vs-ink/85 to-vs-ink/40" />
            <div aria-hidden="true" className="absolute inset-0 bg-[radial-gradient(60rem_30rem_at_85%_-10%,rgba(37,99,235,0.28),transparent)]" />

            <div className={`${landingContainer} relative grid items-center gap-14 pb-20 pt-16 lg:grid-cols-[1.05fr_0.95fr] lg:gap-16 lg:pb-28 lg:pt-24`}>
                <div className="vs-rise">
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-vs-cyan sm:text-sm">A plataforma inteligente para sua empresa crescer</p>
                    <h1 id="hero-title" className="vs-title-hero mt-5 text-balance text-white">
                        Seu crescimento é o nosso propósito.
                    </h1>
                    <p className="mt-6 max-w-xl text-base leading-relaxed text-slate-300 sm:text-lg">
                        Conquiste oportunidades, organize seus atendimentos e acompanhe seus resultados com site, gestão e ferramentas inteligentes em uma única plataforma.
                    </p>
                    <div className="mt-9 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:gap-4">
                        <Link href={register()} className={landingButtonPrimary}>
                            Venha crescer com a gente
                        </Link>
                        <a href="#funcionalidades" className={landingButtonOnDark}>
                            Conheça a plataforma
                        </a>
                    </div>
                </div>

                <div className="vs-rise">
                    <ElectricianAppIllustration />
                </div>
            </div>
        </section>
    );
}
