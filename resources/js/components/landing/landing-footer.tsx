import { Link } from '@inertiajs/react';
import { BadgeCheck } from 'lucide-react';
import { home, login, register } from '@/routes';
import { LandingWordmark } from './landing-header';
import { landingContainer, landingFocusRing } from './landing-layout';

const productLinks = [
    { label: 'Funcionalidades', href: '#funcionalidades' },
    { label: 'Como funciona', href: '#como-funciona' },
    { label: 'IA no atendimento', href: '#atendimento-ia' },
    { label: 'Segmentos', href: '#segmentos' },
    { label: 'Preços', href: '#planos' },
];

const linkClass = `rounded-sm py-1 text-sm text-slate-600 transition-colors hover:text-vs-text ${landingFocusRing}`;

const headingClass = 'text-sm font-semibold uppercase tracking-wider text-vs-text';

/** Stitch footer layout. Only links to pages that exist: no privacy or terms link until those pages are published. */
export function LandingFooter() {
    return (
        <footer className="bg-vs-canvas-blue shadow-[0_-1px_6px_rgba(15,23,42,0.02)]">
            <div className={`${landingContainer} py-16 lg:py-[4.5rem]`}>
                <div className="grid gap-8 border-b border-vs-line pb-12 md:grid-cols-2 lg:grid-cols-4">
                    <div className="flex flex-col gap-4 lg:col-span-2">
                        <Link href={home()} className={`w-fit rounded-md ${landingFocusRing}`} aria-label="VetorSmart, início">
                            <LandingWordmark size="sm" />
                        </Link>
                        <p className="max-w-sm text-base leading-relaxed text-slate-600">
                            A plataforma para prestadores de serviços organizarem atendimento e gestão e crescerem de verdade.
                        </p>
                        <span className="inline-flex w-fit items-center gap-1 rounded-full bg-white/70 px-3 py-1 text-xs font-semibold text-vs-muted">
                            <BadgeCheck aria-hidden="true" className="size-3.5 text-vs-blue-hover" />
                            Uma solução desenvolvida por ABrasilSistemas
                        </span>
                    </div>

                    <nav aria-label="Rodapé, produto" className="flex flex-col gap-2">
                        <p className={headingClass}>Produto</p>
                        <ul className="grid gap-1">
                            {productLinks.map((link) => (
                                <li key={link.href}>
                                    <a href={link.href} className={linkClass}>{link.label}</a>
                                </li>
                            ))}
                        </ul>
                    </nav>

                    <nav aria-label="Rodapé, conta" className="flex flex-col gap-2">
                        <p className={headingClass}>Conta</p>
                        <ul className="grid gap-1">
                            <li><Link href={login()} className={linkClass}>Entrar</Link></li>
                            <li><Link href={register()} className={linkClass}>Criar conta</Link></li>
                            <li><a href="#faq" className={linkClass}>Perguntas frequentes</a></li>
                        </ul>
                    </nav>
                </div>

                <div className="flex flex-col items-center justify-between gap-4 pt-8 text-sm text-slate-600 sm:flex-row">
                    <p>© {new Date().getFullYear()} VetorSmart. Todos os direitos reservados.</p>
                    <p className="font-medium text-vs-text">VetorSmart — Venha crescer com a gente.</p>
                </div>
            </div>
        </footer>
    );
}
