/** Where a request came from and who conducted it (GROWTH-03): three separate views, computed on the server. */
export type TraceabilityItem = {
    key: string;
    label: string;
    detail?: string | null;
};

export type Traceability = {
    commercial_origin: TraceabilityItem;
    channel: TraceabilityItem;
    attendance: TraceabilityItem;
};

const rows: { field: keyof Traceability; title: string }[] = [
    { field: 'commercial_origin', title: 'Origem comercial' },
    { field: 'channel', title: 'Canal de entrada' },
    { field: 'attendance', title: 'Atendimento' },
];

function Value({
    item,
    highlight,
}: {
    item: TraceabilityItem;
    highlight: boolean;
}) {
    return (
        <>
            {highlight ? (
                <span className="inline-flex items-center rounded-full border border-vs-blue/40 bg-vs-blue/10 px-2 py-0.5 text-xs font-medium text-vs-link">
                    {item.label}
                </span>
            ) : (
                <span className="text-vs-text">{item.label}</span>
            )}
            {item.detail && (
                <span className="text-vs-muted"> · {item.detail}</span>
            )}
        </>
    );
}

/** Rows for a definition list (request detail). */
export function TraceabilityRows({ value }: { value: Traceability }) {
    return (
        <>
            {rows.map(({ field, title }) => (
                <div key={field}>
                    <dt className="text-vs-muted">{title}</dt>
                    <dd className="break-words">
                        <Value
                            item={value[field]}
                            highlight={
                                field === 'commercial_origin' &&
                                value[field].key === 'portal'
                            }
                        />
                    </dd>
                </div>
            ))}
        </>
    );
}

/** Compact version for a list entry (customer history): one line per view, wrapping on small screens. */
export function TraceabilityInline({ value }: { value: Traceability }) {
    return (
        <dl className="mt-1 grid gap-x-4 gap-y-1 text-xs sm:grid-cols-3">
            {rows.map(({ field, title }) => (
                <div key={field} className="min-w-0">
                    <dt className="text-vs-muted">{title}</dt>
                    <dd className="break-words">
                        <Value
                            item={value[field]}
                            highlight={
                                field === 'commercial_origin' &&
                                value[field].key === 'portal'
                            }
                        />
                    </dd>
                </div>
            ))}
        </dl>
    );
}
