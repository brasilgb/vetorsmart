import '@inertiajs/core';
import type { Auth } from '@/types/auth';
import type { TenantSummary } from '@/types/vetor-smart';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            tenant: TenantSummary | null;
            tenants: TenantSummary[];
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
