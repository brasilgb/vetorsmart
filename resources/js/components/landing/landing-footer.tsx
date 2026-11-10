import { Link } from '@inertiajs/react';
import { home, login, register } from '@/routes';
import { landingContainer, landingFocusRing } from './landing-layout';

const sectionLinks = [
    { label: 'Plataforma', href: '#plataforma' },
    { label: 'Funcionalidades', href: '#funcionalidades' },
    { label: 'Segmentos', href: '#segmentos' },
    { label: 'Planos', href: '#planos' },
    { label: 'Dúvidas', href: '#faq' },
];

const linkClass = `rounded-sm text-vs-muted transition-colors hover:text-vs-text ${landingFocusRing}`;

/** Compact footer. Only links to pages that exist: no privacy or terms link until those pages are published. */
export function LandingFooter() {
    return (
        <footer className="border-t border-vs-line bg-white">
            <div className={`${landingContainer} grid gap-10 py-12 md:grid-cols-[1.4fr_1fr_1fr]`}>
                <div>
                    <Link href={home()} className={`inline-flex items-center gap-2.5 rounded-md text-base font-semibold text-vs-text ${landingFocusRing}`}>
                        <img src="/images/vetor-smart-logo-reduced.png" alt="" width={28} height={20} className="size-7 object-contain" />
                        VetorSmart
                    </Link>
                    <p className="mt-4 max-w-sm text-sm leading-relaxed text-vs-muted">
                        Plataforma para empresas prestadoras de serviços: site, gestão, atendimento e crescimento em um só lugar.
                    </p>
                    <p className="mt-4 text-sm font-medium text-vs-text">Seu crescimento é o nosso propósito.</p>
                </div>

                <nav aria-label="Rodapé, páginas da landing">
                    <p className="text-xs font-semibold uppercase tracking-[0.14em] text-vs-muted">Landing</p>
                    <ul className="mt-4 grid gap-3 text-sm">
                        {sectionLinks.map((link) => (
                            <li key={link.href}>
                                <a href={link.href} className={linkClass}>{link.label}</a>
                            </li>
                        ))}
                    </ul>
                </nav>

                <nav aria-label="Rodapé, conta">
                    <p className="text-xs font-semibold uppercase tracking-[0.14em] text-vs-muted">Conta</p>
                    <ul className="mt-4 grid gap-3 text-sm">
                        <li><Link href={login()} className={linkClass}>Entrar</Link></li>
                        <li><Link href={register()} className={linkClass}>Criar conta</Link></li>
                    </ul>
                    <p className="mt-8 text-xs leading-relaxed text-vs-muted">ABrasilSistemas</p>
                </nav>
            </div>
        </footer>
    );
}
