import type { ReactNode } from 'react';

export function EmptyState({ children }: { children: ReactNode }) {
    return (
        <div className="rounded-xl border border-dashed border-vs-border px-6 py-10 text-center text-sm text-vs-muted">
            {children}
        </div>
    );
}
