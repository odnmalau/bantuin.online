import { Head, Link } from '@inertiajs/react';
import { Eye, Inbox, Trophy } from 'lucide-react';
import AssessmentStatusBadge from '@/components/assessment-status-badge';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import admin from '@/routes/admin';

type Summary = {
    total_ranked: number;
    pending_approval: number;
    needs_manual_review: number;
    average_ranking_score: number | null;
};

type Weights = {
    resume_score: number;
    essay_score: number;
    mcq_score: number;
};

type SectionScore = {
    section_id: number | null;
    title: string;
    weight: number;
    earned_points: number;
    total_points: number;
    score: number | null;
};

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
    matched_skills: string[];
    missing_skills: string[];
    interview_probes: string[];
    section_scores: SectionScore[];
    evaluated_at: string | null;
};

type Props = {
    formula: string;
    weights: Weights;
    summary: Summary;
    rankings: RankingRow[];
};

export default function AdminRankingsIndex({
    formula,
    weights,
    summary,
    rankings,
}: Props) {
    return (
        <>
            <Head title="Candidate Rankings" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Candidate Rankings"
                    description="Transparent ranking from resume screening, essay grading, and deterministic objective scoring."
                />

                <div className="grid gap-4 md:grid-cols-4">
                    <Metric label="Ranked" value={summary.total_ranked} />
                    <Metric
                        label="Average"
                        value={summary.average_ranking_score ?? '-'}
                    />
                    <Metric
                        label="Pending approval"
                        value={summary.pending_approval}
                    />
                    <Metric
                        label="Manual review"
                        value={summary.needs_manual_review}
                    />
                </div>

                <section className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 className="text-base font-medium">
                                Ranking Formula
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {formula}
                            </p>
                        </div>
                        <div className="grid grid-cols-3 gap-3 text-sm">
                            <Weight
                                label="Resume"
                                value={weights.resume_score}
                            />
                            <Weight label="Essay" value={weights.essay_score} />
                            <Weight label="MCQ" value={weights.mcq_score} />
                        </div>
                    </div>
                </section>

                {rankings.length === 0 ? (
                    <div className="rounded-lg border border-sidebar-border/70 p-8 text-center dark:border-sidebar-border">
                        <Inbox className="mx-auto size-8 text-muted-foreground" />
                        <h2 className="mt-3 text-base font-medium">
                            No ranked candidates yet
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Candidates appear here after AI evaluation produces
                            a ranking score.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[1180px] text-sm">
                                <thead className="border-b bg-muted/40 text-left">
                                    <tr>
                                        <th className="w-20 px-4 py-3 font-medium">
                                            Rank
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Candidate
                                        </th>
                                        <th className="w-48 px-4 py-3 font-medium">
                                            Campaign
                                        </th>
                                        <th className="w-32 px-4 py-3 font-medium">
                                            Score
                                        </th>
                                        <th className="w-48 px-4 py-3 font-medium">
                                            Components
                                        </th>
                                        <th className="w-64 px-4 py-3 font-medium">
                                            Signals
                                        </th>
                                        <th className="w-44 px-4 py-3 font-medium">
                                            Section scores
                                        </th>
                                        <th className="w-40 px-4 py-3 font-medium">
                                            Status
                                        </th>
                                        <th className="w-24 px-4 py-3 text-right font-medium">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {rankings.map((ranking) => (
                                        <tr key={ranking.assessment_id}>
                                            <td className="px-4 py-4 align-top">
                                                <div className="inline-flex items-center gap-2 font-semibold">
                                                    <Trophy className="size-4 text-muted-foreground" />
                                                    {ranking.rank}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <div className="space-y-1">
                                                    <p className="font-medium">
                                                        {ranking.candidate_name ??
                                                            'Unknown candidate'}
                                                    </p>
                                                    <p className="text-muted-foreground">
                                                        {ranking.candidate_email ??
                                                            '-'}
                                                    </p>
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <p className="font-medium">
                                                    {ranking.role_title ?? '-'}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {ranking.campaign_title ??
                                                        '-'}
                                                </p>
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <p className="text-2xl font-semibold">
                                                    {ranking.ranking_score ??
                                                        '-'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Evaluated{' '}
                                                    {ranking.evaluated_at ??
                                                        '-'}
                                                </p>
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <ScorePill
                                                    label="Resume"
                                                    value={ranking.resume_score}
                                                />
                                                <ScorePill
                                                    label="Essay"
                                                    value={ranking.essay_score}
                                                />
                                                <ScorePill
                                                    label="MCQ"
                                                    value={ranking.mcq_score}
                                                />
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <SignalList
                                                    label="Matched"
                                                    items={
                                                        ranking.matched_skills
                                                    }
                                                />
                                                <SignalList
                                                    label="Missing"
                                                    items={
                                                        ranking.missing_skills
                                                    }
                                                />
                                                <SignalList
                                                    label="Probes"
                                                    items={
                                                        ranking.interview_probes
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                {ranking.section_scores.length >
                                                0 ? (
                                                    <div className="space-y-2">
                                                        {ranking.section_scores.map(
                                                            (section) => (
                                                                <div
                                                                    key={`${ranking.assessment_id}-${section.section_id}-${section.title}`}
                                                                    className="text-xs"
                                                                >
                                                                    <p className="font-medium">
                                                                        {
                                                                            section.title
                                                                        }
                                                                    </p>
                                                                    <p className="text-muted-foreground">
                                                                        {section.score ??
                                                                            '-'}
                                                                        % ·
                                                                        weight{' '}
                                                                        {
                                                                            section.weight
                                                                        }
                                                                    </p>
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        -
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <div className="space-y-2">
                                                    <AssessmentStatusBadge
                                                        status={ranking.status}
                                                    />
                                                    {ranking.needs_manual_review && (
                                                        <p className="text-xs font-medium text-amber-600 dark:text-amber-400">
                                                            Needs review
                                                        </p>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <div className="flex justify-end">
                                                    <Button
                                                        size="icon"
                                                        variant="outline"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={admin.assessments.show(
                                                                ranking.assessment_id,
                                                            )}
                                                            aria-label="Review assessment"
                                                        >
                                                            <Eye />
                                                        </Link>
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

function Metric({ label, value }: { label: string; value: number | string }) {
    return (
        <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="mt-1 text-2xl font-semibold">{value}</p>
        </div>
    );
}

function Weight({ label, value }: { label: string; value: number }) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="font-medium">{value}%</p>
        </div>
    );
}

function ScorePill({ label, value }: { label: string; value: number | null }) {
    return (
        <div className="mb-1 flex items-center justify-between gap-3 rounded-md bg-muted/50 px-2 py-1 text-xs">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium">{value ?? '-'}</span>
        </div>
    );
}

function SignalList({ label, items }: { label: string; items: string[] }) {
    if (items.length === 0) {
        return null;
    }

    return (
        <div className="mb-2">
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className="mt-1 line-clamp-2 text-xs">{items.join(', ')}</p>
        </div>
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
