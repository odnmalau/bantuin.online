import { Deferred, Head, usePage } from '@inertiajs/react';
import {
    RankingOverviewSection,
    type NeedsAttention,
    type RankingOverviewCharts,
    type RankingOverviewSummary,
} from '@/components/admin/ranking-overview-section';
import {
    Card,
    CardContent,
    CardHeader,
} from '@/components/ui/card';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { Skeleton } from '@/components/ui/skeleton';
import { dashboard } from '@/routes';
import type { SharedData } from '@/types';

type Overview = {
    has_ranked_candidates: boolean;
    summary: RankingOverviewSummary;
    charts: RankingOverviewCharts;
    needs_attention: NeedsAttention;
};

type Props = {
    overview?: Overview | null;
};

function DashboardOverviewSkeleton() {
    return (
        <div className="flex flex-col gap-4">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {Array.from({ length: 4 }).map((_, index) => (
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

            <Skeleton className="h-11 w-full rounded-md" />

            <div className="grid gap-4 xl:grid-cols-2">
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
                            <RankingOverviewSection
                                summary={overview.summary}
                                charts={overview.charts}
                                needsAttention={overview.needs_attention}
                                hasRankedCandidates={
                                    overview.has_ranked_candidates
                                }
                            />
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
