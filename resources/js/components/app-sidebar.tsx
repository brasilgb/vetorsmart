import { Link, usePage } from '@inertiajs/react';
import { Bot, Briefcase, Building2, CalendarDays, ClipboardList, Contact, CreditCard, Globe, KeyRound, LayoutDashboard, LayoutGrid, LifeBuoy, MessageSquare, ScrollText, Settings, Tag, TrendingUp, UsersRound } from 'lucide-react';
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
import { show as tenantSettings } from '@/routes/tenant/settings';
import { index as tenantWebsite } from '@/routes/tenant/website';
import { index as auditIndex } from '@/routes/rootadmin/audit';
import type { NavItem } from '@/types';

const tenantNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Clientes', href: tenantCustomers(), icon: Contact },
    { title: 'Solicitações', href: tenantRequests(), icon: ClipboardList },
    { title: 'Atendimento IA', href: tenantAssistant(), icon: MessageSquare },
    { title: 'Agenda', href: tenantAgenda(), icon: CalendarDays },
    { title: 'Serviços', href: tenantServices(), icon: Briefcase },
    { title: 'Profissionais', href: tenantProfessionals(), icon: UsersRound },
    { title: 'Usuários e permissões', href: tenantTeam(), icon: KeyRound },
    { title: 'Meu Site', href: tenantWebsite(), icon: Globe },
    { title: 'Configurações', href: tenantSettings(), icon: Settings },
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
    { title: 'Assinaturas e Financeiro', href: rootadminDashboard(), icon: CreditCard, disabled: true },
    { title: 'IA e Créditos', href: rootadminDashboard(), icon: Bot, disabled: true },
    { title: 'Sites e Domínios', href: rootadminDashboard(), icon: Globe, disabled: true },
    { title: 'Segmentos', href: segmentsIndex(), icon: Tag },
    { title: 'Usuários e Acessos', href: usersIndex(), icon: UsersRound },
    { title: 'Crescimento e SEO', href: rootadminDashboard(), icon: TrendingUp, disabled: true },
    { title: 'Suporte', href: rootadminDashboard(), icon: LifeBuoy, disabled: true },
    { title: 'Segurança e Auditoria', href: auditIndex(), icon: ScrollText },
    { title: 'Configurações Globais', href: rootadminDashboard(), icon: Settings, disabled: true },
];

export function AppSidebar() {
    const { auth, tenant } = usePage().props;
    const isRootAdmin = Boolean(auth.user.is_root_admin);
    const canManageTeam = tenant?.role === 'owner' || tenant?.role === 'admin';
    const items = isRootAdmin ? rootadminNavItems : tenantNavItems.filter((item) => item.title !== 'Usuários e permissões' || canManageTeam);

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
                <NavMain items={items} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
