import {
    BriefcaseBusiness,
    FileText,
    LayoutDashboard,
    Trophy,
} from 'lucide-react';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import { exam } from '@/routes/candidate';
import type { Auth, NavItem } from '@/types';

type NavigationSurface = 'header' | 'sidebar';
type NavigationContext = 'workspace' | 'candidate';

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
        title: 'Assessments',
        href: exam(),
        icon: FileText,
    },
];

const workspaceReturnNavItem: NavItem = {
    title: 'Workspace',
    href: dashboard(),
    icon: LayoutDashboard,
};

export function resolveMainNavItems(
    auth: Auth,
    surface: NavigationSurface,
    context: NavigationContext = 'workspace',
): NavItem[] {
    if (context === 'candidate') {
        return [
            ...(auth.capabilities.candidateWork ? candidateNavItems : []),
            ...(auth.capabilities.viewCampaigns
                ? [workspaceReturnNavItem]
                : []),
        ];
    }

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

export function primaryMainNavHref(
    auth?: Auth,
    candidateMode = false,
): NavItem['href'] {
    if (
        candidateMode ||
        (auth?.capabilities.candidateWork === true &&
            auth.capabilities.viewCampaigns === false)
    ) {
        return exam();
    }

    return dashboard();
}
