import { BriefcaseBusiness, FileText, LayoutGrid, Trophy } from 'lucide-react';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import { exam } from '@/routes/candidate';
import type { Auth, NavItem } from '@/types';

type NavigationSurface = 'header' | 'sidebar';

const defaultNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const dashboardNavItem = defaultNavItems[0];

const adminHeaderNavItems: NavItem[] = [
    {
        title: 'Campaigns',
        href: admin.campaigns.index(),
    },
    {
        title: 'Rankings',
        href: admin.rankings.index(),
    },
];

const adminSidebarNavItems: NavItem[] = [
    dashboardNavItem,
    {
        title: 'Campaigns',
        href: admin.campaigns.index(),
        icon: BriefcaseBusiness,
    },
    {
        title: 'Rankings',
        href: admin.rankings.index(),
        icon: Trophy,
    },
];

const candidateNavItems: NavItem[] = [
    {
        title: 'Exam',
        href: exam(),
        icon: FileText,
    },
];

export function resolveMainNavItems(
    auth: Auth,
    surface: NavigationSurface,
): NavItem[] {
    if (auth.capabilities.viewCampaigns) {
        return surface === 'header'
            ? adminHeaderNavItems
            : adminSidebarNavItems;
    }

    if (auth.capabilities.candidateWork) {
        return candidateNavItems;
    }

    return defaultNavItems;
}

export function primaryMainNavHref(): NavItem['href'] {
    return dashboard();
}
