import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { register } from '@/routes';
import { landingContainer, landingFocusRing, landingSectionSpacing } from './landing-layout';
import { segmentIconFor } from './segment-icons';

export type LandingSegment = { id: number; name: string; description: string | null };

/**
 * Segments from the database as a static mosaic: 2 columns on phones, 3 on tablets and 5 on
 * desktop, so all of them are visible at once. Each card links to the sign-up flow, the only
 * destination that accepts a segment today.
 */
export function SegmentMosaic({ segments }: { segments: LandingSegment[] }) {
    if (segments.length === 0) {
        return null;
    }

    return (
        <section id="segmentos" aria-labelledby="segmentos-title" className={`bg-vs-surface ${landingSectionSpacing} scroll-mt-20`}>
            <div className={landingContainer}>
                <div className="max-w-2xl">
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-vs-ink-blue">Segmentos</p>
                    <h2 id="segmentos-title" className="vs-title-section mt-3 text-vs-text">Empresas de serviços que a VetorSmart atende</h2>
                </div>

                <ul className="mt-12 grid grid-cols-2 gap-4 sm:grid-cols-3 sm:gap-5 lg:mt-14 lg:grid-cols-5">
                    {segments.map((segment) => {
                        const Icon = segmentIconFor(segment.name);

                        return (
                            <li key={segment.id} className="flex">
                                <Link
                                    href={register()}
                                    className={`group flex w-full flex-col gap-4 rounded-2xl border border-vs-line bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-vs-blue/40 hover:shadow-md motion-reduce:transition-none motion-reduce:hover:translate-y-0 sm:p-6 ${landingFocusRing}`}
                                >
                                    <span className="flex size-11 items-center justify-center rounded-xl bg-vs-tint-blue text-vs-ink-blue">
                                        <Icon aria-hidden="true" className="size-5" strokeWidth={1.75} />
                                    </span>
                                    <span className="flex flex-1 flex-col gap-1.5">
                                        <span className="text-base font-semibold leading-snug text-vs-text sm:text-lg">{segment.name}</span>
                                        {segment.description && <span className="line-clamp-2 text-sm leading-relaxed text-vs-muted">{segment.description}</span>}
                                    </span>
                                    <span className="inline-flex items-center gap-1.5 text-sm font-medium text-vs-ink-blue">
                                        Criar conta
                                        <ArrowRight aria-hidden="true" className="size-4 transition-transform duration-200 group-hover:translate-x-0.5 motion-reduce:transition-none" />
                                    </span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </div>
        </section>
    );
}
