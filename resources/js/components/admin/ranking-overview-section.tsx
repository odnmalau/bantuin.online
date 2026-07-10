import {
    AlertCircle,
    ClipboardList,
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
    type ChartConfig,
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { cn } from '@/lib/utils';

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
    };
};

export type AverageScoreTrendPoint = {
    date: string;
    label: string;
    average_score: number | null;
    ranked_count: number;
};

export type ScoreDistributionPoint = {
    bucket: string;
    label: string;
    count: number;
};

export type RankingOverviewCharts = {
    average_score_trend: AverageScoreTrendPoint[];
    score_distribution: ScoreDistributionPoint[];
};

const averageScoreTrendConfig = {
    average_score: {
        label: 'Average score',
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

function AverageScoreTrendChart({
    data,
    averageScore,
}: {
    data: AverageScoreTrendPoint[];
    averageScore: number | null;
}) {
    const chartData = data.map((point) => ({
        ...point,
        average_score: point.average_score ?? undefined,
    }));

    return (
        <Card>
            <CardHeader>
                <CardTitle>Average score</CardTitle>
                <CardDescription>
                    Daily average ranking score over the last 7 days.
                </CardDescription>
                <CardAction>
                    <div className="text-right">
                        <p className="text-xs text-muted-foreground">Current</p>
                        <p className="text-2xl font-semibold tabular-nums">
                            {averageScore ?? '-'}
                        </p>
                    </div>
                </CardAction>
            </CardHeader>
            <CardContent>
                <ChartContainer
                    config={averageScoreTrendConfig}
                    className="aspect-[2/1] w-full"
                >
                    <AreaChart
                        accessibilityLayer
                        data={chartData}
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
                            domain={[0, 100]}
                            tickLine={false}
                            axisLine={false}
                            tickMargin={8}
                            width={32}
                        />
                        <ChartTooltip
                            cursor={false}
                            content={<ChartTooltipContent indicator="line" />}
                        />
                        <Area
                            dataKey="average_score"
                            type="monotone"
                            fill="var(--color-average_score)"
                            fillOpacity={0.2}
                            stroke="var(--color-average_score)"
                            strokeWidth={2}
                            connectNulls={false}
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
                    How ranked candidates spread across score bands.
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

export function RankingOverviewSection({
    summary,
    charts,
}: {
    summary: RankingOverviewSummary;
    charts: RankingOverviewCharts;
}) {
    return (
        <div className="flex flex-col gap-4">
            <div className="grid gap-4 sm:grid-cols-3">
                <SummaryMetric
                    label="Ranked"
                    value={summary.total_ranked}
                    icon={Trophy}
                    periodLabel={summary.period_label}
                    change={summary.changes.total_ranked}
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

            <div className="grid gap-4 xl:grid-cols-2">
                <AverageScoreTrendChart
                    data={charts.average_score_trend}
                    averageScore={summary.average_ranking_score}
                />
                <ScoreDistributionChart data={charts.score_distribution} />
            </div>
        </div>
    );
}
