import { Link } from '@inertiajs/react';
import { ArrowRight, SlidersHorizontal } from 'lucide-react';
import { register } from '@/routes';
import { Eyebrow } from './section-shell';
import { landingContainer, landingFocusRing, landingSectionSpacing } from './landing-layout';
import { segmentIconFor } from './segment-icons';

export type LandingSegment = { id: number; name: string; description: string | null };

/**
 * Segments from the database as Stitch-style cards: 1 column on phones, 2 on tablets and 5 on
 * desktop, so all ten are visible at once. Each card links to the sign-up flow, the only
 * destination that accepts a segment today.
 */
export function SegmentMosaic({ segments }: { segments: LandingSegment[] }) {
    if (segments.length === 0) {
        return null;
    }

    return (
        <section id="segmentos" aria-labelledby="segmentos-title" className={`bg-vs-canvas-blue/40 ${landingSectionSpacing} scroll-mt-20`}>
            <div className={landingContainer}>
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div className="max-w-2xl">
                        <Eyebrow>Segmentos atendidos</Eyebrow>
                        <h2 id="segmentos-title" className="vs-title-section mt-2 text-balance text-vs-text">A solução ideal para prestadores de serviços de todos os portes.</h2>
                    </div>
                    <span className="inline-flex h-fit w-fit items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3.5 py-1.5 text-sm font-semibold text-emerald-800">
                        <SlidersHorizontal aria-hidden="true" className="size-4 text-emerald-600" />
                        Adaptável à rotina do seu negócio
                    </span>
                </div>

                <ul className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-5">
                    {segments.map((segment) => {
                        const Icon = segmentIconFor(segment.name);

                        return (
                            <li key={segment.id} className="flex">
                                <Link
                                    href={register()}
                                    className={`group flex w-full flex-col gap-3 rounded-xl border border-vs-line bg-white p-6 shadow-sm transition-colors hover:border-slate-300 ${landingFocusRing}`}
                                >
                                    <span className="flex items-center gap-3">
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-vs-tint-blue text-vs-ink-blue">
                                            <Icon aria-hidden="true" className="size-5" strokeWidth={1.75} />
                                        </span>
                                        <span className="vs-title-card leading-snug text-vs-text">{segment.name}</span>
                                    </span>
                                    {segment.description && <span className="line-clamp-3 flex-1 text-sm leading-relaxed text-vs-muted">{segment.description}</span>}
                                    <span className="mt-auto inline-flex items-center gap-1.5 text-sm font-semibold text-vs-blue-hover">
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
