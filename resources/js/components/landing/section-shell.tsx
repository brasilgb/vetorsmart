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

export function Eyebrow({ children }: { children: ReactNode }) {
    return <p className="text-xs font-semibold uppercase tracking-[0.18em] text-vs-ink-blue">{children}</p>;
}

/** Status badge: the label is text, so the meaning never depends on colour alone. */
export function StatusBadge({ status }: { status: CapabilityStatus }) {
    return <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${statusClasses[status]}`}>{statusLabels[status]}</span>;
}

export function SectionShell({ id, eyebrow, title, lead, children, tone = 'white' }: { id: string; eyebrow?: string; title: string; lead?: string; children: ReactNode; tone?: 'white' | 'surface' }) {
    const background = tone === 'surface' ? 'bg-vs-surface' : 'bg-white';

    return (
        <section id={id} aria-labelledby={`${id}-title`} className={`${background} ${landingSectionSpacing} scroll-mt-20`}>
            <div className={landingContainer}>
                {eyebrow && <Eyebrow>{eyebrow}</Eyebrow>}
                <h2 id={`${id}-title`} className="vs-title-section mt-3 text-vs-text">{title}</h2>
                {lead && <p className="mt-5 max-w-3xl text-lg leading-relaxed text-vs-muted">{lead}</p>}
                <div className="mt-12 lg:mt-14">{children}</div>
            </div>
        </section>
    );
}
