import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { register } from '@/routes';
import { landingContainer, landingFocusRing, landingSectionSpacing } from './landing-layout';

/** Closing call to action in the Stitch dark card. It uses the existing sign-up flow and asks for nothing beyond it. */
export function FinalCtaSection() {
    return (
        <section aria-labelledby="cta-title" className={`bg-vs-canvas ${landingSectionSpacing}`}>
            <div className={landingContainer}>
                <div className="relative overflow-hidden rounded-3xl bg-vs-night px-6 py-12 text-center text-white shadow-2xl sm:px-12 lg:py-[4.5rem]">
                    <div aria-hidden="true" className="pointer-events-none absolute right-0 top-0 size-96 rounded-full bg-vs-blue-hover/20 blur-3xl" />
                    <div aria-hidden="true" className="pointer-events-none absolute bottom-0 left-0 size-80 rounded-full bg-vs-cyan/15 blur-3xl" />

                    <div className="relative mx-auto flex max-w-3xl flex-col items-center gap-4">
                        <span className="rounded-full bg-white/10 px-3.5 py-1 text-xs font-semibold text-cyan-300">Chegou o momento de dar o próximo passo</span>
                        <h2 id="cta-title" className="vs-title-hero text-balance text-white">Seu próximo cliente pode estar procurando por você agora.</h2>
                        <p className="max-w-2xl text-lg leading-relaxed text-slate-300">
                            Organize seu negócio, facilite o atendimento e prepare sua empresa para crescer com uma plataforma pensada para quem presta serviços.
                        </p>
                        <Link
                            href={register()}
                            className={`mt-2 inline-flex items-center gap-2 rounded-xl bg-vs-blue-hover px-8 py-4 font-display text-lg font-bold text-white shadow-lg transition-all hover:scale-[1.02] hover:bg-vs-blue motion-reduce:hover:scale-100 ${landingFocusRing}`}
                        >
                            Quero começar agora
                            <ArrowRight aria-hidden="true" className="size-5" />
                        </Link>
                        <p className="text-sm text-slate-400">O cadastro é feito em uma única tela.</p>

                        <p className="mt-4 flex w-full flex-col items-center justify-center gap-2 border-t border-white/10 pt-4 text-xs text-slate-400 sm:flex-row sm:gap-4">
                            <span className="font-semibold text-white">VetorSmart — Venha crescer com a gente.</span>
                            <span aria-hidden="true" className="hidden sm:inline">•</span>
                            <span>Desenvolvida por ABrasilSistemas</span>
                        </p>
                    </div>
                </div>
            </div>
        </section>
    );
}
