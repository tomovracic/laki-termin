import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, Map, Users } from 'lucide-react';
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
