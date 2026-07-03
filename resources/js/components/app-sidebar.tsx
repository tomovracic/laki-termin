import { Link, usePage } from '@inertiajs/react';
import { CalendarDays, ClipboardList, LayoutGrid, Map, Trophy, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { LanguageSwitcher } from '@/components/language-switcher';
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
    useSidebar,
} from '@/components/ui/sidebar';
import { useI18n } from '@/lib/i18n';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { t } = useI18n();
    const { state } = useSidebar();
    const { auth } = usePage().props;
    const mainNavItems: NavItem[] = [
        {
            title: t('dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: t('my_reservations'),
            href: '/dashboard/reservations',
            icon: CalendarDays,
        },
        {
            title: t('leagues'),
            href: '/dashboard/leagues',
            icon: Trophy,
        },
    ];

    if (auth.isAdmin) {
        mainNavItems.push(
            {
                title: t('users_overview'),
                href: '/admin/users',
                icon: Users,
            },
            {
                title: t('terrains_overview'),
                href: '/admin/terrains',
                icon: Map,
            },
            {
                title: t('leagues_admin_overview'),
                href: '/admin/leagues',
                icon: Trophy,
            },
            {
                title: t('reports_overview'),
                href: '/admin/reports',
                icon: ClipboardList,
            },
        );
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()}>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                {state !== 'collapsed' && <LanguageSwitcher />}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
