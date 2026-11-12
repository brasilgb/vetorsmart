import type { ReactNode } from 'react';
import { landingContainer, landingSectionSpacing } from './landing-layout';

/** Real state of a capability. Nothing is presented as delivered unless its status says so. */
export type CapabilityStatus = 'available' | 'development' | 'soon';

const statusLabels: Record<CapabilityStatus, string> = {
    available: 'Disponível',
    development: 'Em desenvolvimento',
    soon: 'Em breve',
};

const statusClasses: Record<CapabilityStatus, string> = {
    available: 'bg-vs-tint-green text-vs-ink-green',
    development: 'bg-vs-tint-orange text-vs-ink-orange',
    soon: 'bg-vs-tint-slate text-vs-ink-slate',
};

/** Section backgrounds from the Stitch reference: white, the blue-tinted canvas and its lighter step. */
const toneClasses = {
    white: 'bg-white',
    canvas: 'bg-vs-canvas',
    tint: 'bg-vs-canvas-blue/60',
} as const;

export type SectionTone = keyof typeof toneClasses;

export function Eyebrow({ children }: { children: ReactNode }) {
    return <p className="text-xs font-semibold uppercase tracking-wider text-vs-blue-hover">{children}</p>;
}

/** Status badge: the label is text, so the meaning never depends on colour alone. */
export function StatusBadge({ status }: { status: CapabilityStatus }) {
    return <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${statusClasses[status]}`}>{statusLabels[status]}</span>;
}

type SectionShellProps = {
    id: string;
    eyebrow?: string;
    title: string;
    lead?: ReactNode;
    children: ReactNode;
    tone?: SectionTone;
    align?: 'left' | 'center';
};

/** Section frame: eyebrow, title and lead centred by default, as in the Stitch landing. */
export function SectionShell({ id, eyebrow, title, lead, children, tone = 'white', align = 'center' }: SectionShellProps) {
    const centred = align === 'center';

    return (
        <section id={id} aria-labelledby={`${id}-title`} className={`${toneClasses[tone]} ${landingSectionSpacing} scroll-mt-20`}>
            <div className={landingContainer}>
                <div className={centred ? 'mx-auto max-w-3xl text-center' : 'max-w-3xl'}>
                    {eyebrow && <Eyebrow>{eyebrow}</Eyebrow>}
                    <h2 id={`${id}-title`} className="vs-title-section mt-2 text-balance text-vs-text">{title}</h2>
                    {lead && <p className="mt-3 text-lg leading-relaxed text-vs-muted">{lead}</p>}
                </div>
                <div className="mt-12">{children}</div>
            </div>
        </section>
    );
}
