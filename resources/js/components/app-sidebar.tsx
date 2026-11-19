import { Link, usePage } from '@inertiajs/react';
import { Bell, Bot, Briefcase, Building2, CalendarDays, ClipboardList, Contact, CreditCard, Globe, KeyRound, LayoutDashboard, LayoutGrid, LifeBuoy, MessageSquare, ScrollText, Settings, Tag, TrendingUp, UsersRound } from 'lucide-react';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { dashboard as rootadminDashboard } from '@/routes/rootadmin';
import { index as tenantsIndex } from '@/routes/rootadmin/tenants';
import { index as segmentsIndex } from '@/routes/rootadmin/segments';
import { index as usersIndex } from '@/routes/rootadmin/users';
import { index as tenantCustomers } from '@/routes/tenant/customers';
import { index as tenantRequests } from '@/routes/tenant/requests';
import { index as tenantAssistant } from '@/routes/tenant/assistant';
import { index as tenantAgenda } from '@/routes/tenant/agenda';
import { index as tenantServices } from '@/routes/tenant/services';
import { index as tenantProfessionals } from '@/routes/tenant/professionals';
import { index as tenantTeam } from '@/routes/tenant/team';
import { index as tenantNotifications } from '@/routes/tenant/notifications';
import { show as tenantSubscription } from '@/routes/tenant/subscription';
import { show as tenantSettings } from '@/routes/tenant/settings';
import { index as tenantWebsite } from '@/routes/tenant/website';
import { index as tenantGrowth } from '@/routes/tenant/growth';
import { index as tenantSupport } from '@/routes/tenant/support';
import { index as auditIndex } from '@/routes/rootadmin/audit';
import { index as rootadminSubscriptions } from '@/routes/rootadmin/subscriptions';
import { index as rootadminAi } from '@/routes/rootadmin/ai';
import { index as rootadminWebsites } from '@/routes/rootadmin/websites';
import { index as rootadminGrowth } from '@/routes/rootadmin/growth';
import { index as rootadminSupport } from '@/routes/rootadmin/support';
import { show as rootadminSettings } from '@/routes/rootadmin/settings';
import type { NavGroup, NavItem } from '@/types';

/**
 * Company panel, grouped by what the person is doing. Only screens that exist are listed; managersOnly items are
 * hidden from operators (the server authorizes every page as well).
 */
const tenantNavGroups: NavGroup[] = [
    {
        label: 'Visão Geral',
        items: [
            { title: 'Painel', href: dashboard(), icon: LayoutGrid },
            { title: 'Meu Crescimento', href: tenantGrowth(), icon: TrendingUp, managersOnly: true },
            { title: 'Notificações', href: tenantNotifications(), icon: Bell },
        ],
    },
    {
        label: 'Atendimento e Operação',
        items: [
            { title: 'Solicitações', href: tenantRequests(), icon: ClipboardList },
            { title: 'Agenda', href: tenantAgenda(), icon: CalendarDays },
            { title: 'Atendimento IA', href: tenantAssistant(), icon: MessageSquare },
            { title: 'Clientes', href: tenantCustomers(), icon: Contact },
            { title: 'Serviços', href: tenantServices(), icon: Briefcase },
            { title: 'Profissionais', href: tenantProfessionals(), icon: UsersRound },
        ],
    },
    {
        label: 'Presença Digital',
        items: [{ title: 'Meu Site', href: tenantWebsite(), icon: Globe }],
    },
    {
        label: 'Administração',
        items: [
            { title: 'Usuários e permissões', href: tenantTeam(), icon: KeyRound, managersOnly: true },
            { title: 'Minha Assinatura', href: tenantSubscription(), icon: CreditCard, managersOnly: true },
            { title: 'Configurações', href: tenantSettings(), icon: Settings },
            { title: 'Suporte', href: tenantSupport(), icon: LifeBuoy, managersOnly: true },
        ],
    },
];

/**
 * The platform console has its own menu, grouped by responsibility. Areas without a screen yet are
 * listed as disabled, so they do not look operational.
 *
 * The platform console has its own menu. Tenant users never see it, and the console
 * never shows tenant operational menus, so the two areas stay separated.
 */
const rootadminNavItems: NavItem[] = [
    { title: 'Visão Geral', href: rootadminDashboard(), icon: LayoutDashboard },
    { title: 'Empresas', href: tenantsIndex(), icon: Building2 },
    { title: 'Assinaturas e Financeiro', href: rootadminSubscriptions(), icon: CreditCard },
    { title: 'IA e Créditos', href: rootadminAi(), icon: Bot },
    { title: 'Sites e Domínios', href: rootadminWebsites(), icon: Globe },
    { title: 'Segmentos', href: segmentsIndex(), icon: Tag },
    { title: 'Usuários e Acessos', href: usersIndex(), icon: UsersRound },
    { title: 'Crescimento e SEO', href: rootadminGrowth(), icon: TrendingUp },
    { title: 'Suporte', href: rootadminSupport(), icon: LifeBuoy },
    { title: 'Segurança e Auditoria', href: auditIndex(), icon: ScrollText },
    { title: 'Configurações Globais', href: rootadminSettings(), icon: Settings },
];

export function AppSidebar() {
    const { auth, tenant } = usePage().props;
    const isRootAdmin = Boolean(auth.user.is_root_admin);
    const canManageTeam = tenant?.role === 'owner' || tenant?.role === 'admin';
    const groups: NavGroup[] = isRootAdmin
        ? [{ label: 'Plataforma', items: rootadminNavItems }]
        : tenantNavGroups
              .map((group) => ({ ...group, items: group.items.filter((item) => !item.managersOnly || canManageTeam) }))
              .filter((group) => group.items.length > 0);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={isRootAdmin ? rootadminDashboard() : dashboard()} prefetch>
                                <img
                                    src="/images/vetor-smart-logo-reduced.png"
                                    alt="Logo VetorSmart"
                                    width={32}
                                    height={23}
                                    className="size-8 object-contain"
                                />
                                <span className="truncate font-semibold">VetorSmart</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {groups.map((group) => (
                    <NavMain key={group.label} label={group.label} items={group.items} />
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
