import { Link } from '@inertiajs/react';
import { register } from '@/routes';
import { landingButtonPrimary, landingContainer, landingSectionSpacing } from './landing-layout';

/** Closing call to action. It uses the existing sign-up flow and asks for nothing beyond it. */
export function FinalCtaSection() {
    return (
        <section aria-labelledby="cta-title" className={`bg-vs-surface ${landingSectionSpacing}`}>
            <div className={landingContainer}>
                <div className="relative overflow-hidden rounded-3xl bg-vs-ink px-6 py-16 text-center text-white sm:px-12 lg:py-20">
                    <div aria-hidden="true" className="absolute inset-0 bg-[radial-gradient(40rem_20rem_at_50%_0%,rgba(37,99,235,0.35),transparent)]" />
                    <div className="relative mx-auto max-w-2xl">
                        <h2 id="cta-title" className="vs-title-section text-balance text-white">Pronto para dar o próximo passo?</h2>
                        <p className="mt-5 text-base leading-relaxed text-slate-300 sm:text-lg">Sua empresa merece uma plataforma que acompanhe seu crescimento.</p>
                        <div className="mt-10">
                            <Link href={register()} className={landingButtonPrimary}>
                                Venha crescer com a gente!
                            </Link>
                        </div>
                        <p className="mt-5 text-sm text-slate-400">O cadastro é feito em uma única tela.</p>
                    </div>
                </div>
            </div>
        </section>
    );
}
