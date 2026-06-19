import {
    BookOpen,
    BriefcaseBusiness,
    ClipboardList,
    FileText,
    LayoutGrid,
    SlidersHorizontal,
    Trophy,
} from 'lucide-react';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import { exam } from '@/routes/candidate';
import type { NavItem, User } from '@/types';

type NavigationSurface = 'header' | 'sidebar';

const defaultNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const adminHeaderNavItems: NavItem[] = [
    {
        title: 'Rankings',
        href: admin.rankings.index(),
        icon: Trophy,
    },
    {
        title: 'Workstation',
        href: admin.assessments.index(),
        icon: ClipboardList,
    },
];

const adminSidebarNavItems: NavItem[] = [
    {
        title: 'Campaigns',
        href: admin.campaigns.index(),
        icon: BriefcaseBusiness,
    },
    {
        title: 'Question Libraries',
        href: admin.questionBanks.index(),
        icon: BookOpen,
    },
    ...adminHeaderNavItems,
    {
        title: 'Settings',
        href: admin.assessmentSettings.edit(),
        icon: SlidersHorizontal,
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
    role: User['role'] | undefined,
    surface: NavigationSurface,
): NavItem[] {
    if (role === 'admin') {
        return surface === 'sidebar'
            ? adminSidebarNavItems
            : adminHeaderNavItems;
    }

    if (role === 'candidate') {
        return candidateNavItems;
    }

    return defaultNavItems;
}

export function primaryMainNavHref(
    role: User['role'] | undefined,
): NavItem['href'] {
    return resolveMainNavItems(role, 'sidebar')[0]?.href ?? dashboard();
}
