import type { ReactNode } from 'react';

type Props = {
    subtitle: string;
    children: ReactNode;
};

/**
 * Shared frame of every authentication screen (login, registration, password recovery,
 * reset, confirmation and two-factor): full-viewport brand background, a light dark-blue
 * film, and one solid white card. The `vs-auth` class pins the light tokens, so fields stay
 * legible even when the app runs in dark mode. The page scrolls only when the viewport is
 * too short for the card.
 */
export default function AuthShell({ subtitle, children }: Props) {
    return (
        <div
            className="vs-auth relative min-h-dvh w-full bg-vs-ink bg-cover bg-center bg-no-repeat"
            style={{ backgroundImage: "url('/images/auth-background.svg')" }}
        >
            <div className="absolute inset-0 bg-[rgba(15,23,42,0.55)]" />

            <main className="relative z-10 flex min-h-dvh items-center justify-center px-4 py-6 sm:py-8">
                <section className="w-full max-w-[460px] rounded-[20px] bg-white p-6 shadow-[0_24px_60px_-24px_rgba(15,23,42,0.55)] sm:p-8">
                    <div className="flex flex-col items-center text-center">
                        <img
                            src="/images/vetor-smart-logo-reduced.png"
                            alt="Logo VetorSmart"
                            width={64}
                            height={46}
                            className="h-auto w-14 sm:w-16"
                        />
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
