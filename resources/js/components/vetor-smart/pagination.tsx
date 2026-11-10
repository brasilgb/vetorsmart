import { Link } from '@inertiajs/react';
import type { PaginationLink } from '@/types';

/** Laravel paginator labels arrive with HTML entities for the arrows; they are shown as plain text. */
function plainLabel(label: string): string {
    return label.replace('&laquo;', '«').replace('&raquo;', '»');
}

export function Pagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav aria-label="Paginação" className="flex flex-wrap gap-2">
            {links.map((link) =>
                link.url === null ? (
                    <span key={link.label} className="rounded-md px-3 py-1 text-sm text-vs-muted/60">
                        {plainLabel(link.label)}
                    </span>
                ) : (
                    <Link
                        key={link.label}
                        href={link.url}
                        preserveState
                        className={`rounded-md border px-3 py-1 text-sm ${link.active ? 'border-vs-blue bg-vs-blue text-white' : 'border-vs-border text-vs-text hover:bg-vs-surface'}`}
                    >
                        {plainLabel(link.label)}
                    </Link>
                ),
            )}
        </nav>
    );
}
