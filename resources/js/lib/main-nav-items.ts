import {
    BriefcaseBusiness,
    FileText,
    Headphones,
    LayoutGrid,
    Trophy,
} from 'lucide-react';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import { exam } from '@/routes/candidate';
import { index as supportTeams } from '@/routes/support/teams';
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

const supportNavItems: NavItem[] = [
    { title: 'Platform Support', href: supportTeams(), icon: Headphones },
];

export function resolveMainNavItems(
    auth: Auth,
    surface: NavigationSurface,
    supportMode = false,
): NavItem[] {
    if (supportMode) {
        return auth.platformOperator ? supportNavItems : [];
    }

    const teamItems = auth.capabilities.viewCampaigns
        ? surface === 'header'
            ? adminHeaderNavItems
            : adminSidebarNavItems
        : [];
    const candidateItems = auth.capabilities.candidateWork
        ? candidateNavItems
        : [];
    const supportItems = auth.platformOperator ? supportNavItems : [];

    if (teamItems.length > 0 || candidateItems.length > 0) {
        return [...teamItems, ...candidateItems, ...supportItems];
    }

    return [...defaultNavItems, ...supportItems];
}

export function primaryMainNavHref(): NavItem['href'] {
    return dashboard();
}
