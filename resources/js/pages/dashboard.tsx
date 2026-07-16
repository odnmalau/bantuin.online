import { Deferred, Head, Link, usePage } from '@inertiajs/react';
import { RankingOverviewSection } from '@/components/admin/ranking-overview-section';
import type {
    NeedsAttention,
    RankingOverviewCharts,
    RankingOverviewSummary,
} from '@/components/admin/ranking-overview-section';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { dashboard } from '@/routes';
import { exam } from '@/routes/candidate';
import type { SharedData } from '@/types';

type Overview = {
    has_ranked_candidates: boolean;
    summary: RankingOverviewSummary;
    charts: RankingOverviewCharts;
    needs_attention: NeedsAttention;
};

type Props = {
    overview?: Overview | null;
    personalLanding: boolean;
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

export default function Dashboard({ overview, personalLanding }: Props) {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-6 p-4">
                {auth.capabilities.viewCampaigns ? (
                    <Deferred
                        data="overview"
                        fallback={<DashboardOverviewSkeleton />}
                    >
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
                ) : personalLanding ? (
                    <Card className="overflow-hidden border-primary/20 bg-linear-to-br from-primary/8 via-background to-background">
                        <CardHeader>
                            <CardTitle>Your candidate work</CardTitle>
                            <CardDescription>
                                Continue assessment work here, or create a Team
                                when you are ready to run a hiring campaign.
                            </CardDescription>
                        </CardHeader>
                        <CardFooter>
                            <Button asChild variant="outline">
                                <Link href={exam()}>Open candidate work</Link>
                            </Button>
                        </CardFooter>
                    </Card>
                ) : null}
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
