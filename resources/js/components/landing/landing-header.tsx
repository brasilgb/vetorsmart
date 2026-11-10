import { Link, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import type { MouseEvent } from 'react';
import { dashboard, home, login, register } from '@/routes';
import { landingContainer, landingFocusRing } from './landing-layout';

const navItems = [
    { label: 'Plataforma', href: '#plataforma' },
    { label: 'Funcionalidades', href: '#funcionalidades' },
    { label: 'Segmentos', href: '#segmentos' },
    { label: 'Planos', href: '#planos' },
];

const linkClass = `rounded-md px-3 py-2 text-sm font-medium text-vs-muted transition-colors hover:text-vs-text ${landingFocusRing}`;

/**
 * Public header: logo, four anchors and the two account actions. On phones the anchors move into a
 * disclosure menu, so the primary call to action keeps its place in the bar.
 */
export function LandingHeader() {
    const { auth } = usePage().props;

    const closeMenu = (event: MouseEvent<HTMLAnchorElement>) => {
        event.currentTarget.closest('details')?.removeAttribute('open');
    };

    return (
        <header className="sticky top-0 z-30 border-b border-vs-line bg-white/95 backdrop-blur">
            <nav aria-label="Principal" className={`${landingContainer} flex h-16 items-center justify-between gap-4`}>
                <Link href={home()} className={`flex items-center gap-2.5 rounded-md text-base font-semibold text-vs-text ${landingFocusRing}`} aria-label="VetorSmart, início">
                    <img src="/images/vetor-smart-logo-reduced.png" alt="" width={32} height={23} className="size-8 object-contain" />
                    VetorSmart
                </Link>

                <ul className="hidden items-center gap-1 lg:flex">
                    {navItems.map((item) => (
                        <li key={item.href}>
                            <a href={item.href} className={linkClass}>{item.label}</a>
                        </li>
                    ))}
                </ul>

                <div className="flex items-center gap-1 sm:gap-2">
                    {auth.user ? (
                        <Link href={dashboard()} className={`whitespace-nowrap rounded-lg bg-vs-blue px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-vs-blue-hover ${landingFocusRing}`}>
                            Ir para o painel
                        </Link>
                    ) : (
                        <>
                            <Link href={login()} className={`hidden rounded-md px-3 py-2 text-sm font-medium text-vs-muted transition-colors hover:text-vs-text sm:inline-flex ${landingFocusRing}`}>
                                Entrar
                            </Link>
                            <Link href={register()} className={`whitespace-nowrap rounded-lg bg-vs-blue px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-vs-blue-hover ${landingFocusRing}`}>
                                Começar grátis
                            </Link>
                        </>
                    )}

                    <details className="group relative lg:hidden">
                        <summary className={`flex cursor-pointer list-none items-center gap-1 whitespace-nowrap rounded-md px-3 py-2.5 text-sm font-medium text-vs-text marker:content-none ${landingFocusRing}`}>
                            Menu
                            <ChevronDown aria-hidden="true" className="size-4 transition-transform group-open:rotate-180" />
                        </summary>
                        <div className="absolute right-0 top-full z-40 mt-2 w-64 rounded-xl border border-vs-line bg-white p-2 shadow-lg">
                            <ul className="grid">
                                {navItems.map((item) => (
                                    <li key={item.href}>
                                        <a href={item.href} onClick={closeMenu} className={`block rounded-lg px-3 py-3 text-sm font-medium text-vs-text hover:bg-vs-surface ${landingFocusRing}`}>{item.label}</a>
                                    </li>
                                ))}
                                {!auth.user && (
                                    <li className="mt-1 border-t border-vs-line pt-1">
                                        <Link href={login()} className={`block rounded-lg px-3 py-3 text-sm font-medium text-vs-text hover:bg-vs-surface ${landingFocusRing}`}>Entrar</Link>
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
