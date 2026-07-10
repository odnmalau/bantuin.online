import { Deferred, Head, usePage } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import {
    RankingOverviewSection,
    type RankingOverviewCharts,
    type RankingOverviewSummary,
} from '@/components/admin/ranking-overview-section';
import {
    RecentCampaignsSection,
    type RecentCampaign,
} from '@/components/admin/recent-campaigns-section';
import {
    Card,
    CardContent,
    CardHeader,
} from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { Skeleton } from '@/components/ui/skeleton';
import { dashboard } from '@/routes';
import type { SharedData } from '@/types';

type Overview = {
    summary: RankingOverviewSummary;
    charts: RankingOverviewCharts;
    recent_campaigns: RecentCampaign[];
};

type Props = {
    overview?: Overview | null;
};

function DashboardOverviewSkeleton() {
    return (
        <div className="flex flex-col gap-4">
            <div className="grid gap-4 sm:grid-cols-3">
                {Array.from({ length: 3 }).map((_, index) => (
                    <Card key={index} size="sm">
                        <CardHeader>
                            <Skeleton className="h-4 w-24" />
                            <Skeleton className="size-9 rounded-full" />
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            <Skeleton className="h-9 w-16" />
                            <Skeleton className="h-5 w-28" />
                        </CardContent>
                    </Card>
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                {Array.from({ length: 2 }).map((_, index) => (
                    <Card key={index}>
                        <CardHeader>
                            <Skeleton className="h-5 w-32" />
                            <Skeleton className="h-4 w-48" />
                        </CardHeader>
                        <CardContent>
                            <Skeleton className="aspect-[2/1] w-full" />
                        </CardContent>
                    </Card>
                ))}
            </div>

            <div className="flex flex-col gap-3">
                <Skeleton className="h-5 w-40" />
                <Skeleton className="h-4 w-64" />
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {Array.from({ length: 3 }).map((_, index) => (
                        <Card key={index} size="sm">
                            <CardHeader>
                                <Skeleton className="h-5 w-36" />
                                <Skeleton className="h-5 w-16" />
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <Skeleton className="h-4 w-28" />
                                <div className="grid grid-cols-2 gap-2">
                                    <Skeleton className="h-14 w-full" />
                                    <Skeleton className="h-14 w-full" />
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </div>
    );
}

export default function Dashboard({ overview }: Props) {
    const { auth } = usePage<SharedData>().props;
    const isAdmin = auth.user?.role === 'admin';

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-6 p-4">
                {isAdmin ? (
                    <Deferred data="overview" fallback={<DashboardOverviewSkeleton />}>
                        {overview ? (
                            <div className="flex flex-col gap-4">
                                <RankingOverviewSection
                                    summary={overview.summary}
                                    charts={overview.charts}
                                />

                                {overview.summary.total_ranked === 0 &&
                                overview.recent_campaigns.length > 0 ? (
                                    <Empty className="border border-dashed">
                                        <EmptyHeader>
                                            <EmptyMedia variant="icon">
                                                <Inbox />
                                            </EmptyMedia>
                                            <EmptyTitle>
                                                No ranked candidates yet
                                            </EmptyTitle>
                                            <EmptyDescription>
                                                Ranked candidates and score
                                                trends will appear here after
                                                assessments are evaluated.
                                            </EmptyDescription>
                                        </EmptyHeader>
                                    </Empty>
                                ) : null}

                                <RecentCampaignsSection
                                    campaigns={overview.recent_campaigns}
                                />
                            </div>
                        ) : null}
                    </Deferred>
                ) : (
                    <div className="relative min-h-[320px] overflow-hidden rounded-xl border border-sidebar-border/70">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
