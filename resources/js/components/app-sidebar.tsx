import { Link, usePage } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TeamSwitcher } from '@/components/team-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from '@/components/ui/sidebar';
import { primaryMainNavHref, resolveMainNavItems } from '@/lib/main-nav-items';
import type { Auth } from '@/types';

export function AppSidebar() {
    const page = usePage<{ auth: Auth }>();
    const { auth } = page.props;
    const isSupportMode = page.url.startsWith('/support');
    const mainNavItems = resolveMainNavItems(auth, 'sidebar');

    return (
        <Sidebar collapsible="offcanvas" variant="sidebar">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={primaryMainNavHref(auth)} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                {isSupportMode ? (
                    <div className="rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm font-medium text-amber-800 dark:text-amber-200">
                        Platform Support mode
                    </div>
                ) : (
                    <TeamSwitcher className="w-full justify-between" />
                )}
            </SidebarHeader>

            {mainNavItems.length > 0 && (
                <SidebarContent>
                    <NavMain items={mainNavItems} />
                </SidebarContent>
            )}

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>

            <SidebarRail />
        </Sidebar>
    );
}
