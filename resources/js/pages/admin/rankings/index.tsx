import { Head, Link, router, useForm } from '@inertiajs/react';
import { Inbox, Medal } from 'lucide-react';
import type { FormEvent } from 'react';
import AssessmentStatusBadge from '@/components/assessment-status-badge';
import { PaginationControls } from '@/components/pagination-controls';
import type { Paginated } from '@/components/pagination-controls';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import {
    Item,
    ItemActions,
    ItemContent,
    ItemDescription,
    ItemGroup,
    ItemTitle,
} from '@/components/ui/item';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import admin from '@/routes/admin';

type RankingRow = {
    rank: number;
    assessment_id: number;
    candidate_name: string | null;
    candidate_email: string | null;
    campaign_title: string | null;
    role_title: string | null;
    ranking_score: number | null;
    resume_score: number | null;
    assessment_score: number | null;
    status: string;
    needs_manual_review: boolean;
    evaluated_at: string | null;
};

type RankingFilters = {
    campaign: string;
    search: string;
    status: string;
    date_range: string;
};

type SelectOption = {
    value: string;
    label: string;
};

type Props = {
    rankings: Paginated<RankingRow>;
    filters: RankingFilters;
    campaignOptions: SelectOption[];
    statusOptions: SelectOption[];
    dateRangeOptions: SelectOption[];
};

function scoreValue(score: number | null) {
    return score ?? '-';
}

function candidateInitials(name: string | null) {
    if (!name) {
        return '?';
    }

    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

function formatEvaluatedAt(value: string | null) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function RankBadge({ rank }: { rank: number }) {
    if (rank <= 3) {
        return (
            <Badge variant={rank === 1 ? 'default' : 'secondary'}>
                <Medal data-icon="inline-start" />#{rank}
            </Badge>
        );
    }

    return <Badge variant="outline">#{rank}</Badge>;
}

function ScoreBreakdown({ ranking }: { ranking: RankingRow }) {
    return (
        <div className="text-right">
            <p className="font-semibold tabular-nums">
                {scoreValue(ranking.ranking_score)}
            </p>
            <p className="text-xs text-muted-foreground">
                R {scoreValue(ranking.resume_score)} · A{' '}
                {scoreValue(ranking.assessment_score)}
            </p>
        </div>
    );
}

function StatusCell({ ranking }: { ranking: RankingRow }) {
    return (
        <div className="flex flex-col items-start gap-1.5">
            <AssessmentStatusBadge status={ranking.status} />
            {ranking.needs_manual_review ? (
                <Badge variant="outline">Needs review</Badge>
            ) : null}
        </div>
    );
}

export default function AdminRankingsIndex({
    rankings,
    filters,
    campaignOptions,
    statusOptions,
    dateRangeOptions,
}: Props) {
    const { data, setData, get } = useForm<RankingFilters>({
        campaign: filters.campaign ?? '',
        search: filters.search ?? '',
        status: filters.status ?? 'all',
        date_range: filters.date_range ?? 'all',
    });

    const hasSecondaryFilters =
        data.search.trim() !== '' ||
        data.status !== 'all' ||
        data.date_range !== 'all';

    const hasCampaignOptions = campaignOptions.length > 0;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        get(admin.rankings.index.url(), {
            preserveScroll: true,
            preserveState: true,
        });
    }

    function applyFilters(nextFilters: RankingFilters) {
        setData(nextFilters);

        router.get(admin.rankings.index.url(), nextFilters, {
            preserveScroll: true,
            preserveState: true,
        });
    }

    return (
        <>
            <Head title="Candidate Rankings" />

            <div className="flex flex-col gap-6 p-4">
                <form
                    onSubmit={submit}
                    className="flex flex-col gap-2 lg:flex-row"
                >
                    <Select
                        value={data.campaign || undefined}
                        onValueChange={(campaign) =>
                            applyFilters({
                                ...data,
                                campaign,
                            })
                        }
                        disabled={!hasCampaignOptions}
                    >
                        <SelectTrigger className="w-full lg:w-64">
                            <SelectValue placeholder="Select campaign" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                {campaignOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <Input
                        type="search"
                        value={data.search}
                        onChange={(event) =>
                            setData('search', event.target.value)
                        }
                        placeholder="Search by candidate or email"
                        className="flex-1"
                        disabled={!hasCampaignOptions}
                    />
                    <Select
                        value={data.status}
                        onValueChange={(status) =>
                            applyFilters({
                                ...data,
                                status,
                            })
                        }
                        disabled={!hasCampaignOptions}
                    >
                        <SelectTrigger className="w-full lg:w-48">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">
                                    All statuses
                                </SelectItem>
                                {statusOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <Select
                        value={data.date_range}
                        onValueChange={(date_range) =>
                            applyFilters({
                                ...data,
                                date_range,
                            })
                        }
                        disabled={!hasCampaignOptions}
                    >
                        <SelectTrigger className="w-full lg:w-44">
                            <SelectValue placeholder="Date range" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                {dateRangeOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </form>

                {rankings.data.length === 0 ? (
                    <Empty className="border border-dashed">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Inbox />
                            </EmptyMedia>
                            <EmptyTitle>
                                {!hasCampaignOptions
                                    ? 'No ranked candidates yet'
                                    : hasSecondaryFilters
                                      ? 'No matching candidates'
                                      : 'No ranked candidates in this campaign'}
                            </EmptyTitle>
                            <EmptyDescription>
                                {!hasCampaignOptions
                                    ? 'Candidates appear here after AI evaluation produces a ranking score.'
                                    : hasSecondaryFilters
                                      ? 'Try a different search or clear the filters.'
                                      : 'Select another campaign or wait for evaluations to finish.'}
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <div className="flex flex-col gap-4">
                        <ItemGroup className="md:hidden" data-size="sm">
                            {rankings.data.map((ranking) => (
                                <Item
                                    key={ranking.assessment_id}
                                    asChild
                                    variant="outline"
                                    size="sm"
                                >
                                    <Link
                                        href={admin.assessments.show(
                                            ranking.assessment_id,
                                        )}
                                        prefetch
                                        aria-label={`Review ${ranking.candidate_name ?? 'candidate'}`}
                                    >
                                        <ItemActions className="shrink-0">
                                            <RankBadge rank={ranking.rank} />
                                        </ItemActions>
                                        <ItemContent>
                                            <ItemTitle>
                                                {ranking.candidate_name ??
                                                    'Unknown candidate'}
                                            </ItemTitle>
                                            <ItemDescription>
                                                {ranking.candidate_email ?? '-'}
                                            </ItemDescription>
                                            <ItemDescription>
                                                Evaluated{' '}
                                                {formatEvaluatedAt(
                                                    ranking.evaluated_at,
                                                )}
                                            </ItemDescription>
                                        </ItemContent>
                                        <ItemActions className="ml-auto flex-col items-end gap-1.5">
                                            <ScoreBreakdown ranking={ranking} />
                                            <StatusCell ranking={ranking} />
                                        </ItemActions>
                                    </Link>
                                </Item>
                            ))}
                        </ItemGroup>

                        <Card className="hidden py-0 md:block">
                            <CardContent className="px-0">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-20 pl-4">
                                                Rank
                                            </TableHead>
                                            <TableHead>Candidate</TableHead>
                                            <TableHead className="pr-4 text-right">
                                                Score
                                            </TableHead>
                                            <TableHead className="pr-4">
                                                Status
                                            </TableHead>
                                            <TableHead className="pr-4">
                                                Evaluated
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {rankings.data.map((ranking) => (
                                            <TableRow
                                                key={ranking.assessment_id}
                                            >
                                                <TableCell className="pl-4">
                                                    <RankBadge
                                                        rank={ranking.rank}
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-3">
                                                        <Avatar size="sm">
                                                            <AvatarFallback>
                                                                {candidateInitials(
                                                                    ranking.candidate_name,
                                                                )}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <div className="min-w-0">
                                                            <Link
                                                                href={admin.assessments.show(
                                                                    ranking.assessment_id,
                                                                )}
                                                                prefetch
                                                                className="truncate font-medium underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                                                            >
                                                                {ranking.candidate_name ??
                                                                    'Unknown candidate'}
                                                            </Link>
                                                            <p className="truncate text-muted-foreground">
                                                                {ranking.candidate_email ??
                                                                    '-'}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="pr-4">
                                                    <ScoreBreakdown
                                                        ranking={ranking}
                                                    />
                                                </TableCell>
                                                <TableCell className="pr-4">
                                                    <StatusCell
                                                        ranking={ranking}
                                                    />
                                                </TableCell>
                                                <TableCell className="pr-4 text-muted-foreground">
                                                    {formatEvaluatedAt(
                                                        ranking.evaluated_at,
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>

                        <PaginationControls paginator={rankings} />
                    </div>
                )}
            </div>
        </>
    );
}

AdminRankingsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Candidate Rankings',
            href: admin.rankings.index(),
        },
    ],
};
