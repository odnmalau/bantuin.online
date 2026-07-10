import { Head, router, useForm } from '@inertiajs/react';
import { Inbox, Medal } from 'lucide-react';
import type { FormEvent, KeyboardEvent } from 'react';
import AssessmentStatusBadge from '@/components/assessment-status-badge';
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
    essay_score: number | null;
    mcq_score: number | null;
    status: string;
    needs_manual_review: boolean;
};

type RankingFilters = {
    search: string;
    status: string;
    date_range: string;
};

type SelectOption = {
    value: string;
    label: string;
};

type Props = {
    rankings: RankingRow[];
    filters: RankingFilters;
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

export default function AdminRankingsIndex({
    rankings,
    filters,
    statusOptions,
    dateRangeOptions,
}: Props) {
    const { data, setData, get } = useForm<RankingFilters>({
        search: filters.search ?? '',
        status: filters.status ?? 'all',
        date_range: filters.date_range ?? 'all',
    });

    const hasActiveFilters =
        data.search.trim() !== '' ||
        data.status !== 'all' ||
        data.date_range !== 'all';

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
                    <Input
                        type="search"
                        value={data.search}
                        onChange={(event) =>
                            setData('search', event.target.value)
                        }
                        placeholder="Search by candidate, email, campaign, or role"
                        className="flex-1"
                    />
                    <Select
                        value={data.status}
                        onValueChange={(status) =>
                            applyFilters({
                                ...data,
                                status,
                            })
                        }
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

                {rankings.length === 0 ? (
                    <Empty className="border border-dashed">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Inbox />
                            </EmptyMedia>
                            <EmptyTitle>
                                {hasActiveFilters
                                    ? 'No matching candidates'
                                    : 'No ranked candidates yet'}
                            </EmptyTitle>
                            <EmptyDescription>
                                {hasActiveFilters
                                    ? 'Try a different search or clear the filters.'
                                    : 'Candidates appear here after AI evaluation produces a ranking score.'}
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <Card className="py-0">
                        <CardContent className="px-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-20 pl-4">
                                            Rank
                                        </TableHead>
                                        <TableHead>Candidate</TableHead>
                                        <TableHead>Campaign</TableHead>
                                        <TableHead className="pr-4 text-right">
                                            Score
                                        </TableHead>
                                        <TableHead className="pr-4">
                                            Status
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rankings.map((ranking) => {
                                        const reviewUrl =
                                            admin.assessments.show.url(
                                                ranking.assessment_id,
                                            );

                                        function openReview() {
                                            router.visit(reviewUrl);
                                        }

                                        function handleKeyDown(
                                            event: KeyboardEvent<HTMLTableRowElement>,
                                        ) {
                                            if (
                                                event.key === 'Enter' ||
                                                event.key === ' '
                                            ) {
                                                event.preventDefault();
                                                openReview();
                                            }
                                        }

                                        return (
                                            <TableRow
                                                key={ranking.assessment_id}
                                                className="cursor-pointer"
                                                tabIndex={0}
                                                role="link"
                                                aria-label={`Review ${ranking.candidate_name ?? 'candidate'}`}
                                                onClick={openReview}
                                                onKeyDown={handleKeyDown}
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
                                                            <p className="truncate font-medium">
                                                                {ranking.candidate_name ??
                                                                    'Unknown candidate'}
                                                            </p>
                                                            <p className="truncate text-muted-foreground">
                                                                {ranking.candidate_email ??
                                                                    '-'}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="min-w-0">
                                                        <p className="truncate font-medium">
                                                            {ranking.role_title ??
                                                                '-'}
                                                        </p>
                                                        <p className="truncate text-muted-foreground">
                                                            {ranking.campaign_title ??
                                                                '-'}
                                                        </p>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="pr-4 text-right">
                                                    <p className="font-semibold tabular-nums">
                                                        {scoreValue(
                                                            ranking.ranking_score,
                                                        )}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        R{' '}
                                                        {scoreValue(
                                                            ranking.resume_score,
                                                        )}{' '}
                                                        · E{' '}
                                                        {scoreValue(
                                                            ranking.essay_score,
                                                        )}{' '}
                                                        · M{' '}
                                                        {scoreValue(
                                                            ranking.mcq_score,
                                                        )}
                                                    </p>
                                                </TableCell>
                                                <TableCell className="pr-4">
                                                    <div className="flex flex-col items-start gap-1.5">
                                                        <AssessmentStatusBadge
                                                            status={
                                                                ranking.status
                                                            }
                                                        />
                                                        {ranking.needs_manual_review ? (
                                                            <Badge variant="outline">
                                                                Needs review
                                                            </Badge>
                                                        ) : null}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
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
