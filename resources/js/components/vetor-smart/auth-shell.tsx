import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { home } from '@/routes';

type Props = {
    subtitle: string;
    children: ReactNode;
};

/**
 * Shared frame of every authentication screen (login, registration, password recovery,
 * reset, confirmation and two-factor): the service photograph of a technician with a tablet,
 * under a navy film, and one solid white card. The logo leads back to the landing page. The
 * `vs-auth` class pins the light tokens, so fields stay legible even when the app runs in dark
 * mode. The page scrolls only when the viewport is too short for the card.
 */
export default function AuthShell({ subtitle, children }: Props) {
    return (
        <div
            className="vs-auth relative min-h-dvh w-full bg-vs-ink bg-cover bg-center bg-no-repeat"
            style={{ backgroundImage: "url('/images/workshop.png')" }}
        >
            <div aria-hidden="true" className="absolute inset-0 bg-gradient-to-br from-vs-ink/90 via-vs-ink/75 to-vs-ink/60" />

            <main className="relative z-10 flex min-h-dvh items-center justify-center px-4 py-6 sm:py-8">
                <section className="w-full max-w-[460px] rounded-[20px] bg-white p-6 shadow-[0_24px_60px_-24px_rgba(15,23,42,0.55)] sm:p-8">
                    <div className="flex flex-col items-center text-center">
                        <Link href={home()} className="rounded-lg outline-offset-4 focus-visible:outline-2 focus-visible:outline-vs-blue" aria-label="VetorSmart, voltar para o início">
                            <img
                                src="/images/vetor-smart-logo-reduced.png"
                                alt=""
                                width={64}
                                height={46}
                                className="h-auto w-14 sm:w-16"
                            />
                        </Link>
                        <h1 className="mt-3 text-2xl font-semibold text-vs-text">
                            VetorSmart
                        </h1>
                        <p className="mt-1 text-sm text-vs-muted">{subtitle}</p>
                    </div>

                    <div className="mt-6">{children}</div>
                </section>
            </main>
        </div>
    );
}
