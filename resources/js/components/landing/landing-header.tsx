import { Link, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import type { MouseEvent } from 'react';
import { dashboard, home, login, register } from '@/routes';
import { landingContainer, landingFocusRing } from './landing-layout';

const navItems = [
    { label: 'Funcionalidades', href: '#funcionalidades' },
    { label: 'Como funciona', href: '#como-funciona' },
    { label: 'Segmentos', href: '#segmentos' },
    { label: 'Preços', href: '#planos' },
    { label: 'Perguntas frequentes', href: '#faq' },
];

const linkClass = `rounded-md px-1 py-1 text-sm font-medium text-slate-600 transition-colors hover:text-vs-text ${landingFocusRing}`;

const ctaClass = `whitespace-nowrap rounded-lg bg-vs-blue-hover px-3 py-2.5 sm:px-4 text-sm font-semibold text-white shadow-sm transition-all hover:bg-vs-ink-blue hover:shadow-[0_4px_12px_rgba(29,78,216,0.2)] active:scale-[0.98] ${landingFocusRing}`;

/** VetorSmart wordmark: the reduced logo in a blue tile, with "Smart" in the brand blue. */
export function LandingWordmark({ size = 'md' }: { size?: 'md' | 'sm' }) {
    const tile = size === 'md' ? 'size-9 sm:size-10' : 'size-9';

    return (
        <span className="flex items-center gap-2">
            <span className={`${tile} flex items-center justify-center rounded-lg bg-vs-blue-hover shadow-[0_2px_8px_rgba(29,78,216,0.24)]`}>
                <img src="/images/vetor-smart-logo-reduced.png" alt="" width={24} height={17} className="size-6 object-contain brightness-0 invert" />
            </span>
            <span className="font-display text-lg font-bold tracking-tight text-vs-text sm:text-xl">
                Vetor<span className="font-extrabold text-vs-blue-hover">Smart</span>
            </span>
        </span>
    );
}

/**
 * Public header: wordmark, five anchors and the two account actions. On phones the anchors move into a
 * disclosure menu, so the primary call to action keeps its place in the bar.
 */
export function LandingHeader() {
    const { auth } = usePage().props;

    const closeMenu = (event: MouseEvent<HTMLAnchorElement>) => {
        event.currentTarget.closest('details')?.removeAttribute('open');
    };

    return (
        <header className="sticky top-0 z-30 bg-white/90 shadow-[0_1px_8px_rgba(15,23,42,0.04)] backdrop-blur-md">
            <nav aria-label="Principal" className={`${landingContainer} flex h-20 items-center justify-between gap-3 sm:gap-6`}>
                <Link href={home()} className={`rounded-md ${landingFocusRing}`} aria-label="VetorSmart, início">
                    <LandingWordmark />
                </Link>

                <ul className="hidden items-center gap-6 xl:flex">
                    {navItems.map((item) => (
                        <li key={item.href}>
                            <a href={item.href} className={linkClass}>{item.label}</a>
                        </li>
                    ))}
                </ul>

                <div className="flex items-center gap-1 sm:gap-3">
                    {auth.user ? (
                        <Link href={dashboard()} className={ctaClass}>
                            Ir para o painel
                        </Link>
                    ) : (
                        <>
                            <Link href={login()} className={`hidden rounded-md px-2 py-1 text-sm font-medium text-slate-600 transition-colors hover:text-vs-text sm:inline-flex ${landingFocusRing}`}>
                                Entrar
                            </Link>
                            <Link href={register()} className={ctaClass}>
                                Começar agora
                            </Link>
                        </>
                    )}

                    <details className="group relative xl:hidden">
                        <summary className={`flex cursor-pointer list-none items-center gap-1 whitespace-nowrap rounded-md px-2 py-2.5 sm:px-3 text-sm font-medium text-vs-text marker:content-none ${landingFocusRing}`}>
                            Menu
                            <ChevronDown aria-hidden="true" className="size-4 transition-transform group-open:rotate-180" />
                        </summary>
                        <div className="absolute right-0 top-full z-40 mt-2 w-64 rounded-xl border border-vs-line bg-white p-2 shadow-lg">
                            <ul className="grid">
                                {navItems.map((item) => (
                                    <li key={item.href}>
                                        <a href={item.href} onClick={closeMenu} className={`block rounded-lg px-3 py-3 text-sm font-medium text-vs-text hover:bg-vs-canvas-blue ${landingFocusRing}`}>{item.label}</a>
                                    </li>
                                ))}
                                {!auth.user && (
                                    <li className="mt-1 border-t border-vs-line pt-1">
                                        <Link href={login()} className={`block rounded-lg px-3 py-3 text-sm font-medium text-vs-text hover:bg-vs-canvas-blue ${landingFocusRing}`}>Entrar</Link>
                                    </li>
                                )}
                            </ul>
                        </div>
                    </details>
                </div>
            </nav>
        </header>
    );
}
