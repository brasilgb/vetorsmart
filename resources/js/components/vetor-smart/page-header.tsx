import type { ReactNode } from 'react';

type Props = {
    title: string;
    description?: string;
    action?: ReactNode;
};

export function PageHeader({ title, description, action }: Props) {
    return (
        <div className="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 className="text-2xl font-semibold text-vs-text">{title}</h1>
                {description && <p className="text-sm text-vs-muted">{description}</p>}
            </div>
            {action}
        </div>
    );
}
