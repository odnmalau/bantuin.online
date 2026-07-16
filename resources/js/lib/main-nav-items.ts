import { BriefcaseBusiness, FileText, Trophy } from 'lucide-react';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import { exam } from '@/routes/candidate';
import type { Auth, NavItem } from '@/types';

type NavigationSurface = 'header' | 'sidebar';

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
    const teamItems = auth.capabilities.viewCampaigns
        ? surface === 'header'
            ? adminHeaderNavItems
            : adminSidebarNavItems
        : [];
    const candidateItems = auth.capabilities.candidateWork
        ? candidateNavItems
        : [];

    return [...teamItems, ...candidateItems];
}

export function primaryMainNavHref(): NavItem['href'] {
    return dashboard();
}
