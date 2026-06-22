import { Head, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import AssessmentController from '@/actions/App/Http/Controllers/Candidate/AssessmentController';
import AssessmentStatusBadge from '@/components/assessment-status-badge';
import Heading from '@/components/heading';
import candidate from '@/routes/candidate';

type AnswerSnapshot = {
    question_id: number;
    question: string;
    answer: string;
};

type Assessment = {
    id: number;
    campaign_id: number | null;
    campaign: {
        title: string;
        role_title: string;
    } | null;
    answers_payload: AnswerSnapshot[];
    resume_original_name: string | null;
    resume_score: number | null;
    ai_score: number | null;
    ai_justification: string | null;
    status: string;
    created_at: string;
    evaluated_at: string | null;
    email_sent_at: string | null;
};

type Props = {
    assessment: Assessment;
};

const PROCESSING_STATUSES = [
    'submitted',
    'resume_processing',
    'resume_screening',
    'evaluating',
];

export default function CandidateAssessmentShow({ assessment }: Props) {
    const { start, stop } = usePoll(
        2000,
        { only: ['assessment'] },
        { autoStart: false, mode: 'rest' },
    );

    useEffect(() => {
        if (PROCESSING_STATUSES.includes(assessment.status)) {
            start();

            return;
        }

        stop();
    }, [assessment.status, start, stop]);

    return (
        <>
            <Head title="Assessment Status" />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Assessment Status"
                        description={
                            assessment.campaign
                                ? `${assessment.campaign.title} - ${assessment.campaign.role_title}`
                                : 'Track your submitted assessment and review your answers.'
                        }
                    />
                    <AssessmentStatusBadge status={assessment.status} />
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">Resume</p>
                        <p className="mt-1 truncate text-sm font-medium">
                            {assessment.resume_original_name ?? '-'}
                        </p>
                    </div>
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            Resume score
                        </p>
                        <p className="mt-1 text-2xl font-semibold">
                            {assessment.resume_score ?? '-'}
                        </p>
                    </div>
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">Score</p>
                        <p className="mt-1 text-2xl font-semibold">
                            {assessment.ai_score ?? '-'}
                        </p>
                    </div>
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            Submitted
                        </p>
                        <p className="mt-1 text-sm font-medium">
                            {assessment.created_at}
                        </p>
                    </div>
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            Evaluated
                        </p>
                        <p className="mt-1 text-sm font-medium">
                            {assessment.evaluated_at ?? '-'}
                        </p>
                    </div>
                </div>

                {assessment.ai_justification && (
                    <section className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                        <h2 className="text-base font-medium">
                            AI justification
                        </h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {assessment.ai_justification}
                        </p>
                    </section>
                )}

                <section className="space-y-4">
                    <h2 className="text-base font-medium">Submitted answers</h2>
                    {assessment.answers_payload.map((answer, index) => (
                        <article
                            key={answer.question_id}
                            className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                        >
                            <div className="space-y-3">
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">
                                        Question {index + 1}
                                    </p>
                                    <h3 className="mt-1 text-sm font-medium">
                                        {answer.question}
                                    </h3>
                                </div>
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">
                                        Answer
                                    </p>
                                    <p className="mt-1 text-sm whitespace-pre-wrap">
                                        {answer.answer}
                                    </p>
                                </div>
                            </div>
                        </article>
                    ))}
                </section>
            </div>
        </>
    );
}

CandidateAssessmentShow.layout = {
    breadcrumbs: (page: { props: Props }) => {
        const examHref =
            page.props.assessment.campaign_id !== null
                ? AssessmentController.campaignExam.url(
                      page.props.assessment.campaign_id,
                  )
                : candidate.exam();

        return [
            {
                title: 'Candidate Exam',
                href: examHref,
            },
            {
                title: 'Assessment Status',
                href: examHref,
            },
        ];
    },
};
