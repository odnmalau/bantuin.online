import { Link } from '@inertiajs/react';
import {
    AlertCircle,
    ChartNoAxesColumn,
    ChevronDown,
    ChevronRight,
    ClipboardList,
    Inbox,
    TrendingDown,
    TrendingUp,
    Trophy,
} from 'lucide-react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    XAxis,
    YAxis,
} from 'recharts';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Item,
    ItemActions,
    ItemContent,
    ItemGroup,
    ItemSeparator,
    ItemTitle,
} from '@/components/ui/item';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';

export type RankingOverviewSummary = {
    total_ranked: number;
    pending_approval: number;
    needs_manual_review: number;
    average_ranking_score: number | null;
    period_label: string;
    changes: {
        total_ranked: number | null;
        pending_approval: number | null;
        needs_manual_review: number | null;
        average_ranking_score: number | null;
    };
};

export type RankingActivityPoint = {
    date: string;
    label: string;
    ranked_count: number;
};

export type ScoreDistributionPoint = {
    bucket: string;
    label: string;
    count: number;
};

export type NeedsAttentionItem = {
    campaign_id: number;
    label: string;
    badge: string;
};

export type NeedsAttention = {
    summary: {
        campaigns: number;
        pending: number;
        manual_reviews: number;
        failures: number;
    };
    items: NeedsAttentionItem[];
};

export type RankingOverviewCharts = {
    ranking_activity: RankingActivityPoint[];
    score_distribution: ScoreDistributionPoint[];
};

const rankingActivityConfig = {
    ranked_count: {
        label: 'Ranked',
        color: 'var(--chart-1)',
    },
} satisfies ChartConfig;

const scoreDistributionConfig = {
    count: {
        label: 'Candidates',
        color: 'var(--chart-2)',
    },
} satisfies ChartConfig;

function formatChangePercent(change: number | null) {
    if (change === null) {
        return null;
    }

    const absolute = Math.abs(change).toFixed(1).replace(/\.0$/, '');

    if (change > 0) {
        return `+${absolute}%`;
    }

    if (change < 0) {
        return `-${absolute}%`;
    }

    return `${absolute}%`;
}

function formatCountLabel(count: number, singular: string, plural: string) {
    return `${count} ${count === 1 ? singular : plural}`;
}

function SummaryMetric({
    label,
    value,
    icon: Icon,
    periodLabel,
    change,
}: {
    label: string;
    value: number | string;
    icon: typeof Trophy;
    periodLabel: string;
    change: number | null;
}) {
    const changeLabel = formatChangePercent(change);
    const isUp = change !== null && change > 0;
    const isDown = change !== null && change < 0;

    return (
        <Card size="sm">
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardAction>
                    <div className="flex size-9 items-center justify-center rounded-full bg-muted text-muted-foreground">
                        <Icon className="size-4" />
                    </div>
                </CardAction>
            </CardHeader>
            <CardContent className="flex flex-col gap-2">
                <CardTitle className="text-3xl tabular-nums">{value}</CardTitle>
                <div className="flex items-center gap-2 text-xs">
                    <span className="text-muted-foreground">{periodLabel}</span>
                    {changeLabel ? (
                        <Badge
                            variant="outline"
                            className={cn(
                                isUp && 'border-chart-2/30 text-chart-2',
                                isDown &&
                                    'border-destructive/30 text-destructive',
                            )}
                        >
                            {isUp ? (
                                <TrendingUp data-icon="inline-start" />
                            ) : null}
                            {isDown ? (
                                <TrendingDown data-icon="inline-start" />
                            ) : null}
                            {changeLabel}
                        </Badge>
                    ) : (
                        <Badge variant="outline">—</Badge>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function RankingActivityChart({ data }: { data: RankingActivityPoint[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Ranking activity</CardTitle>
                <CardDescription>
                    Candidates ranked each day over the last 7 days.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ChartContainer
                    config={rankingActivityConfig}
                    className="aspect-[2/1] w-full"
                >
                    <AreaChart
                        accessibilityLayer
                        data={data}
                        margin={{ left: 8, right: 8, top: 8 }}
                    >
                        <CartesianGrid vertical={false} />
                        <XAxis
                            dataKey="label"
                            tickLine={false}
                            axisLine={false}
                            tickMargin={8}
                        />
                        <YAxis
                            allowDecimals={false}
                            tickLine={false}
                            axisLine={false}
                            tickMargin={8}
                            width={28}
                        />
                        <ChartTooltip
                            cursor={false}
                            content={
                                <ChartTooltipContent indicator="line" />
                            }
                        />
                        <Area
                            dataKey="ranked_count"
                            type="monotone"
                            fill="var(--color-ranked_count)"
                            fillOpacity={0.2}
                            stroke="var(--color-ranked_count)"
                            strokeWidth={2}
                        />
                    </AreaChart>
                </ChartContainer>
            </CardContent>
        </Card>
    );
}

function ScoreDistributionChart({
    data,
}: {
    data: ScoreDistributionPoint[];
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Score distribution</CardTitle>
                <CardDescription>
                    Ranked candidates by score band in the last 7 days.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ChartContainer
                    config={scoreDistributionConfig}
                    className="aspect-[2/1] w-full"
                >
                    <BarChart
                        accessibilityLayer
                        data={data}
                        margin={{ left: 8, right: 8, top: 8 }}
                    >
                        <CartesianGrid vertical={false} />
                        <XAxis
                            dataKey="label"
                            tickLine={false}
                            axisLine={false}
                            tickMargin={8}
                        />
                        <YAxis
                            allowDecimals={false}
                            tickLine={false}
                            axisLine={false}
                            tickMargin={8}
                            width={28}
                        />
                        <ChartTooltip
                            cursor={false}
                            content={<ChartTooltipContent hideLabel />}
                        />
                        <Bar
                            dataKey="count"
                            fill="var(--color-count)"
                            radius={6}
                        />
                    </BarChart>
                </ChartContainer>
            </CardContent>
        </Card>
    );
}

function NeedsAttentionBar({ attention }: { attention: NeedsAttention }) {
    if (attention.summary.campaigns === 0 || attention.items.length === 0) {
        return null;
    }

    const summaryParts = [
        attention.summary.failures > 0
            ? formatCountLabel(
                  attention.summary.failures,
                  'failure',
                  'failures',
              )
            : null,
        attention.summary.pending > 0
            ? formatCountLabel(
                  attention.summary.pending,
                  'pending',
                  'pending',
              )
            : null,
        attention.summary.manual_reviews > 0
            ? formatCountLabel(
                  attention.summary.manual_reviews,
                  'manual review',
                  'manual reviews',
              )
            : null,
    ].filter(Boolean);

    return (
        <Collapsible className="group/attention">
            <Item asChild size="sm" className="border border-border">
                <CollapsibleTrigger>
                    <ItemContent className="min-w-0 flex-row flex-wrap items-center gap-x-3 gap-y-1">
                        <ItemTitle className="flex items-center gap-2">
                            <AlertCircle className="size-4 text-muted-foreground" />
                            {formatCountLabel(
                                attention.summary.campaigns,
                                'campaign needs attention',
                                'campaigns need attention',
                            )}
                        </ItemTitle>
                        {summaryParts.length > 0 ? (
                            <span className="text-sm text-muted-foreground">
                                {summaryParts.join(' · ')}
                            </span>
                        ) : null}
                    </ItemContent>
                    <ItemActions>
                        <ChevronDown className="size-4 text-muted-foreground transition-transform group-data-[state=open]/attention:rotate-180" />
                    </ItemActions>
                </CollapsibleTrigger>
            </Item>

            <CollapsibleContent className="pt-2">
                <ItemGroup className="gap-0 rounded-lg border">
                    {attention.items.map((item, index) => (
                        <div key={item.campaign_id}>
                            {index > 0 ? (
                                <ItemSeparator className="my-0" />
                            ) : null}
                            <Item asChild size="sm" className="rounded-none">
                                <Link
                                    href={admin.campaigns.show.url(
                                        item.campaign_id,
                                    )}
                                >
                                    <ItemContent>
                                        <ItemTitle>{item.label}</ItemTitle>
                                    </ItemContent>
                                    <ItemActions>
                                        <Badge variant="secondary">
                                            {item.badge}
                                        </Badge>
                                        <ChevronRight className="size-4 text-muted-foreground" />
                                    </ItemActions>
                                </Link>
                            </Item>
                        </div>
                    ))}
                </ItemGroup>
            </CollapsibleContent>
        </Collapsible>
    );
}

export function RankingOverviewSection({
    summary,
    charts,
    needsAttention,
    hasRankedCandidates,
}: {
    summary: RankingOverviewSummary;
    charts: RankingOverviewCharts;
    needsAttention: NeedsAttention;
    hasRankedCandidates: boolean;
}) {
    const hasPeriodActivity = summary.total_ranked > 0;

    return (
        <div className="flex flex-col gap-4">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <SummaryMetric
                    label="Ranked"
                    value={summary.total_ranked}
                    icon={Trophy}
                    periodLabel={summary.period_label}
                    change={summary.changes.total_ranked}
                />
                <SummaryMetric
                    label="Average score"
                    value={summary.average_ranking_score ?? '-'}
                    icon={ChartNoAxesColumn}
                    periodLabel={summary.period_label}
                    change={summary.changes.average_ranking_score}
                />
                <SummaryMetric
                    label="Pending approval"
                    value={summary.pending_approval}
                    icon={ClipboardList}
                    periodLabel={summary.period_label}
                    change={summary.changes.pending_approval}
                />
                <SummaryMetric
                    label="Manual review"
                    value={summary.needs_manual_review}
                    icon={AlertCircle}
                    periodLabel={summary.period_label}
                    change={summary.changes.needs_manual_review}
                />
            </div>

            <NeedsAttentionBar attention={needsAttention} />

            {!hasRankedCandidates ? (
                <Empty className="border border-dashed">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <Inbox />
                        </EmptyMedia>
                        <EmptyTitle>No ranked candidates yet</EmptyTitle>
                        <EmptyDescription>
                            Ranked candidates and score trends will appear here
                            after assessments are evaluated.
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : hasPeriodActivity ? (
                <div className="grid gap-4 xl:grid-cols-2">
                    <RankingActivityChart data={charts.ranking_activity} />
                    <ScoreDistributionChart data={charts.score_distribution} />
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">
                    No ranking activity in this period.
                </p>
            )}
        </div>
    );
}
