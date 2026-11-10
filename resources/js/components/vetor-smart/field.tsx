import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';

/**
 * A labelled form field with its validation message. Keeps every form screen consistent.
 */
export function Field({ id, label, error, children, hint }: { id: string; label: string; error?: string; children: ReactNode; hint?: string }) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id} className="text-vs-text">{label}</Label>
            {children}
            {hint && <p className="text-xs text-vs-muted">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

export const selectClass = 'h-10 w-full rounded-md border border-vs-border bg-white px-3 text-sm text-vs-text focus-visible:ring-[3px] focus-visible:ring-vs-blue/30 focus-visible:outline-none';
