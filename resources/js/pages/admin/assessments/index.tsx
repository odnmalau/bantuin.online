import { Head, Link } from '@inertiajs/react';
import { Eye, Inbox } from 'lucide-react';
import AssessmentStatusBadge from '@/components/assessment-status-badge';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import admin from '@/routes/admin';

type AssessmentRow = {
    id: number;
    candidate_name: string | null;
    candidate_email: string | null;
    ai_score: number | null;
    resume_score: number | null;
    mcq_score: number | null;
    essay_score: number | null;
    ranking_score: number | null;
    status: string;
    created_at: string;
    evaluated_at: string | null;
};

type Props = {
    assessments: AssessmentRow[];
};

export default function AdminAssessmentsIndex({ assessments }: Props) {
    return (
        <>
            <Head title="Assessment Workstation" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Assessment Workstation"
                    description="Review candidate submissions and AI evaluation results."
                />

                {assessments.length === 0 ? (
                    <div className="rounded-lg border border-sidebar-border/70 p-8 text-center dark:border-sidebar-border">
                        <Inbox className="mx-auto size-8 text-muted-foreground" />
                        <h2 className="mt-3 text-base font-medium">
                            No assessments yet
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Candidate submissions will appear here after the
                            exam is submitted.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[1020px] text-sm">
                                <thead className="border-b bg-muted/40 text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">
                                            Candidate
                                        </th>
                                        <th className="w-24 px-4 py-3 font-medium">
                                            Ranking
                                        </th>
                                        <th className="w-24 px-4 py-3 font-medium">
                                            Essay
                                        </th>
                                        <th className="w-24 px-4 py-3 font-medium">
                                            Resume
                                        </th>
                                        <th className="w-24 px-4 py-3 font-medium">
                                            MCQ
                                        </th>
                                        <th className="w-40 px-4 py-3 font-medium">
                                            Status
                                        </th>
                                        <th className="w-44 px-4 py-3 font-medium">
                                            Submitted
                                        </th>
                                        <th className="w-44 px-4 py-3 font-medium">
                                            Evaluated
                                        </th>
                                        <th className="w-24 px-4 py-3 text-right font-medium">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {assessments.map((assessment) => (
                                        <tr key={assessment.id}>
                                            <td className="px-4 py-4 align-top">
                                                <div className="space-y-1">
                                                    <p className="font-medium">
                                                        {assessment.candidate_name ??
                                                            'Unknown candidate'}
                                                    </p>
                                                    <p className="text-muted-foreground">
                                                        {assessment.candidate_email ??
                                                            '-'}
                                                    </p>
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 align-top font-medium">
                                                {assessment.ranking_score ??
                                                    '-'}
                                            </td>
                                            <td className="px-4 py-4 align-top text-muted-foreground">
                                                {assessment.essay_score ??
                                                    assessment.ai_score ??
                                                    '-'}
                                            </td>
                                            <td className="px-4 py-4 align-top text-muted-foreground">
                                                {assessment.resume_score ?? '-'}
                                            </td>
                                            <td className="px-4 py-4 align-top text-muted-foreground">
                                                {assessment.mcq_score ?? '-'}
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <AssessmentStatusBadge
                                                    status={assessment.status}
                                                />
                                            </td>
                                            <td className="px-4 py-4 align-top text-muted-foreground">
                                                {assessment.created_at}
                                            </td>
                                            <td className="px-4 py-4 align-top text-muted-foreground">
                                                {assessment.evaluated_at ?? '-'}
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
                                                                assessment.id,
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

AdminAssessmentsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Assessment Workstation',
            href: admin.assessments.index(),
        },
    ],
};
