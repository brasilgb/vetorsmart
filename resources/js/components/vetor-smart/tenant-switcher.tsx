import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { dashboard } from '@/routes';

/**
 * Shows the active company and, when the user belongs to several, lets them choose.
 * The choice goes through the API, which checks that the membership is active before
 * changing the session. The client never sends a trusted tenant decision on its own.
 */
export function TenantSwitcher() {
    const { tenant, tenants } = usePage().props;
    const [error, setError] = useState<string | null>(null);

    if (tenant === null) {
        return null;
    }

    const choose = async (tenantId: number) => {
        setError(null);

        const cookie = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='));
        const token = cookie ? decodeURIComponent(cookie.split('=').slice(1).join('=')) : '';

        const response = await fetch('/api/v1/auth/switch-tenant', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': token,
            },
            body: JSON.stringify({ tenant_id: tenantId }),
        });

        if (!response.ok) {
            setError('Não foi possível trocar de empresa.');

            return;
        }

        router.visit(dashboard());
    };

    if (tenants.length <= 1) {
        return <span className="truncate text-sm font-medium text-vs-text">{tenant.name}</span>;
    }

    return (
        <div className="flex items-center gap-2">
            <label htmlFor="tenant-switcher" className="sr-only">
                Empresa ativa
            </label>
            <select
                id="tenant-switcher"
                value={tenant.id}
                onChange={(event) => choose(Number(event.target.value))}
                className="h-9 max-w-[220px] truncate rounded-md border border-vs-border bg-white px-2 text-sm text-vs-text"
            >
                {tenants.map((item) => (
                    <option key={item.id} value={item.id}>
                        {item.name}
                    </option>
                ))}
            </select>
            {error && <span className="text-xs text-red-600">{error}</span>}
        </div>
    );
}
