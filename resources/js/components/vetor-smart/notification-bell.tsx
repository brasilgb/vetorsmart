import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { index as notificationsIndex } from '@/routes/tenant/notifications';

/**
 * Header shortcut to the company's notices, with the number of unread ones. Shown only inside a company.
 */
export function NotificationBell() {
    const { tenant, unreadNotifications } = usePage().props;

    if (!tenant) {
        return null;
    }

    const count = unreadNotifications ?? 0;
    const label = count === 0 ? 'Notificações, nenhuma nova' : `Notificações, ${count} ${count === 1 ? 'nova' : 'novas'}`;

    return (
        <Link
            href={notificationsIndex.url()}
            aria-label={label}
            title={label}
            className="relative inline-flex size-10 items-center justify-center rounded-md text-vs-text hover:bg-vs-surface focus-visible:ring-[3px] focus-visible:ring-vs-blue/30 focus-visible:outline-none"
        >
            <Bell className="size-5" aria-hidden="true" />
            {count > 0 && (
                <span aria-hidden="true" className="absolute top-1 right-1 min-w-5 rounded-full bg-vs-danger px-1 text-center text-[11px] leading-5 font-semibold text-white">
                    {count > 99 ? '99+' : count}
                </span>
            )}
        </Link>
    );
}
