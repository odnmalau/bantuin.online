import { Form, Head, Link } from '@inertiajs/react';
import {
    Activity,
    ArrowLeft,
    CheckCircle2,
    Mail,
    RotateCcw,
    ShieldCheck,
    SlidersHorizontal,
    TrendingUp,
    XCircle,
} from 'lucide-react';
import AssessmentController from '@/actions/App/Http/Controllers/Admin/AssessmentController';
import AssessmentStatusBadge from '@/components/assessment-status-badge';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import admin from '@/routes/admin';

type AnswerSnapshot = {
    question_id: number;
    question: string;
    rubric: string;
    answer: string;
};

type RankingPayload = {
    components?: {
        resume_score?: number | null;
        essay_score?: number | null;
        mcq_score?: number | null;
    };
    configured_weights?: {
        resume_score?: number;
        essay_score?: number;
        mcq_score?: number;
    };
    normalized_weights?: {
        resume_score?: number;
        essay_score?: number;
        mcq_score?: number;
    };
    missing_components?: string[];
    weighting_mode?: string;
    formula?: string;
};

type CriticPayload = {
    outcome?: string;
    summary?: string;
    findings?: string[];
    manual_review_required?: boolean;
    repaired_email?: {
        subject?: string | null;
        body?: string | null;
    };
};

type AssessmentEvent = {
    id: number;
    type: string;
    title: string;
    description: string | null;
    payload: Record<string, unknown> | null;
    occurred_at: string;
    actor: {
        name: string | null;
        email: string | null;
    } | null;
};

type AuditContext = {
    provider: string;
    model: string | null;
    threshold: number;
    threshold_source: 'campaign' | 'global';
    global_passing_score: number;
    ranking_formula: string;
    override_reason: string | null;
    override_score: number | null;
};

type Assessment = {
    id: number;
    candidate: {
        name: string | null;
        email: string | null;
    };
    approver: {
        name: string;
        email: string;
    } | null;
    answers_payload: AnswerSnapshot[];
    resume_original_name: string | null;
    resume_score: number | null;
    resume_justification: string | null;
    resume_payload: {
        summary?: string;
        matched_skills?: string[];
        missing_skills?: string[];
        risk_flags?: string[];
        interview_probes?: string[];
        confidence?: number;
    } | null;
    needs_manual_review: boolean;
    ai_score: number | null;
    mcq_score: number | null;
    essay_score: number | null;
    ranking_score: number | null;
    ranking_payload: RankingPayload | null;
    critic_payload: CriticPayload | null;
    ai_justification: string | null;
    ai_email_subject: string | null;
    ai_email_body: string | null;
    approved_email_subject: string | null;
    approved_email_body: string | null;
    status: string;
    can_review: boolean;
    can_retry: boolean;
    can_retry_email: boolean;
    can_promote: boolean;
    can_override_score: boolean;
    created_at: string;
    evaluated_at: string | null;
    approved_at: string | null;
    rejected_at: string | null;
    email_sent_at: string | null;
    audit: AuditContext;
    events: AssessmentEvent[];
};

type Props = {
    assessment: Assessment;
};

export default function AdminAssessmentsShow({ assessment }: Props) {
    const subject =
        assessment.approved_email_subject ??
        assessment.ai_email_subject ??
        'Interview Invitation';
    const body =
        assessment.approved_email_body ?? assessment.ai_email_body ?? '';

    return (
        <>
            <Head title="Assessment Review" />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-4">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={admin.assessments.index()}>
                                <ArrowLeft />
                                Back
                            </Link>
                        </Button>
                        <Heading
                            title="Assessment Review"
                            description={`${assessment.candidate.name ?? 'Unknown candidate'} - ${assessment.candidate.email ?? '-'}`}
                        />
                    </div>
                    <AssessmentStatusBadge status={assessment.status} />
                </div>

                <div className="grid gap-4 md:grid-cols-4 xl:grid-cols-7">
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">Ranking</p>
                        <p className="mt-1 text-2xl font-semibold">
                            {assessment.ranking_score ?? '-'}
                        </p>
                    </div>
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">Essay</p>
                        <p className="mt-1 text-2xl font-semibold">
                            {assessment.essay_score ??
                                assessment.ai_score ??
                                '-'}
                        </p>
                    </div>
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">Resume</p>
                        <p className="mt-1 text-2xl font-semibold">
                            {assessment.resume_score ?? '-'}
                        </p>
                    </div>
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">MCQ</p>
                        <p className="mt-1 text-2xl font-semibold">
                            {assessment.mcq_score ?? '-'}
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
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            Approved
                        </p>
                        <p className="mt-1 text-sm font-medium">
                            {assessment.approved_at ?? '-'}
                        </p>
                    </div>
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            Email sent
                        </p>
                        <p className="mt-1 text-sm font-medium">
                            {assessment.email_sent_at ?? '-'}
                        </p>
                    </div>
                </div>

                <RecoveryActions assessment={assessment} />

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(320px,420px)]">
                    <ActivityTimeline events={assessment.events} />
                    <AuditPanel assessment={assessment} />
                </div>

                {assessment.ai_justification && (
                    <section className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                        <h2 className="text-base font-medium">
                            AI Justification
                        </h2>
                        <p className="mt-2 text-sm whitespace-pre-wrap text-muted-foreground">
                            {assessment.ai_justification}
                        </p>
                    </section>
                )}

                {assessment.critic_payload && (
                    <section className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 className="text-base font-medium">
                                    Critic Review
                                </h2>
                                <p className="mt-2 text-sm whitespace-pre-wrap text-muted-foreground">
                                    {assessment.critic_payload.summary ??
                                        'No critic summary available.'}
                                </p>
                            </div>
                            <div className="text-right text-sm">
                                <p className="font-medium">
                                    Outcome:{' '}
                                    {assessment.critic_payload.outcome ?? '-'}
                                </p>
                                {assessment.critic_payload
                                    .manual_review_required && (
                                    <p className="mt-1 font-medium text-amber-600 dark:text-amber-400">
                                        Manual review required
                                    </p>
                                )}
                            </div>
                        </div>

                        {assessment.critic_payload.findings &&
                            assessment.critic_payload.findings.length > 0 && (
                                <ul className="mt-4 list-inside list-disc space-y-1 text-sm text-muted-foreground">
                                    {assessment.critic_payload.findings.map(
                                        (finding) => (
                                            <li key={finding}>{finding}</li>
                                        ),
                                    )}
                                </ul>
                            )}
                    </section>
                )}

                {assessment.ranking_payload && (
                    <section className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 className="text-base font-medium">
                                    Ranking Breakdown
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {assessment.ranking_payload.formula ??
                                        'Weighted score from available components.'}
                                </p>
                            </div>
                            <p className="text-sm font-medium">
                                Mode:{' '}
                                {assessment.ranking_payload.weighting_mode ??
                                    '-'}
                            </p>
                        </div>

                        <div className="mt-5 grid gap-4 md:grid-cols-3">
                            <ScoreComponent
                                label="Resume"
                                score={
                                    assessment.ranking_payload.components
                                        ?.resume_score
                                }
                                configuredWeight={
                                    assessment.ranking_payload
                                        .configured_weights?.resume_score
                                }
                                normalizedWeight={
                                    assessment.ranking_payload
                                        .normalized_weights?.resume_score
                                }
                            />
                            <ScoreComponent
                                label="Essay"
                                score={
                                    assessment.ranking_payload.components
                                        ?.essay_score
                                }
                                configuredWeight={
                                    assessment.ranking_payload
                                        .configured_weights?.essay_score
                                }
                                normalizedWeight={
                                    assessment.ranking_payload
                                        .normalized_weights?.essay_score
                                }
                            />
                            <ScoreComponent
                                label="MCQ"
                                score={
                                    assessment.ranking_payload.components
                                        ?.mcq_score
                                }
                                configuredWeight={
                                    assessment.ranking_payload
                                        .configured_weights?.mcq_score
                                }
                                normalizedWeight={
                                    assessment.ranking_payload
                                        .normalized_weights?.mcq_score
                                }
                            />
                        </div>

                        {assessment.ranking_payload.missing_components &&
                            assessment.ranking_payload.missing_components
                                .length > 0 && (
                                <p className="mt-4 text-sm text-muted-foreground">
                                    Missing components:{' '}
                                    {assessment.ranking_payload.missing_components.join(
                                        ', ',
                                    )}
                                </p>
                            )}
                    </section>
                )}

                <section className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                    <div className="grid gap-5 lg:grid-cols-[220px_1fr]">
                        <div className="space-y-3">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Resume
                                </p>
                                <p className="mt-1 text-sm font-medium">
                                    {assessment.resume_original_name ?? '-'}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Resume score
                                </p>
                                <p className="mt-1 text-2xl font-semibold">
                                    {assessment.resume_score ?? '-'}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Confidence
                                </p>
                                <p className="mt-1 text-sm font-medium">
                                    {assessment.resume_payload?.confidence ??
                                        '-'}
                                </p>
                            </div>
                        </div>

                        <div className="space-y-4">
                            <div>
                                <h2 className="text-base font-medium">
                                    Resume Screening
                                </h2>
                                <p className="mt-2 text-sm whitespace-pre-wrap text-muted-foreground">
                                    {assessment.resume_payload?.summary ??
                                        assessment.resume_justification ??
                                        'No resume screening result yet.'}
                                </p>
                            </div>

                            <ResumeScreeningList
                                title="Matched skills"
                                items={
                                    assessment.resume_payload?.matched_skills ??
                                    []
                                }
                            />
                            <ResumeScreeningList
                                title="Missing skills"
                                items={
                                    assessment.resume_payload?.missing_skills ??
                                    []
                                }
                            />
                            <ResumeScreeningList
                                title="Risk flags"
                                items={
                                    assessment.resume_payload?.risk_flags ?? []
                                }
                            />
                            <ResumeScreeningList
                                title="Interview probes"
                                items={
                                    assessment.resume_payload
                                        ?.interview_probes ?? []
                                }
                            />
                        </div>
                    </div>
                </section>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(360px,440px)]">
                    <section className="space-y-4">
                        <h2 className="text-base font-medium">
                            Answer Snapshot
                        </h2>
                        {assessment.answers_payload.map((answer, index) => (
                            <article
                                key={answer.question_id}
                                className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                            >
                                <div className="space-y-4">
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
                                            Rubric
                                        </p>
                                        <p className="mt-1 text-sm whitespace-pre-wrap">
                                            {answer.rubric}
                                        </p>
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

                    <section className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                        <div className="space-y-5">
                            <div>
                                <h2 className="text-base font-medium">
                                    Interview Email
                                </h2>
                                {!assessment.can_review && (
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        This assessment is not open for review.
                                    </p>
                                )}
                            </div>

                            <Form
                                {...AssessmentController.approve.form(
                                    assessment.id,
                                )}
                                className="space-y-4"
                            >
                                {({ errors, processing }) => {
                                    const formErrors = errors as Record<
                                        string,
                                        string | undefined
                                    >;

                                    return (
                                        <>
                                            <InputError
                                                message={formErrors.assessment}
                                            />

                                            <div className="grid gap-2">
                                                <Label htmlFor="email_subject">
                                                    Subject
                                                </Label>
                                                <input
                                                    id="email_subject"
                                                    name="email_subject"
                                                    defaultValue={subject}
                                                    disabled={
                                                        !assessment.can_review
                                                    }
                                                    className="flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                                />
                                                <InputError
                                                    message={
                                                        formErrors.email_subject
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="email_body">
                                                    Body
                                                </Label>
                                                <textarea
                                                    id="email_body"
                                                    name="email_body"
                                                    rows={10}
                                                    defaultValue={body}
                                                    disabled={
                                                        !assessment.can_review
                                                    }
                                                    className="flex min-h-56 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                                />
                                                <InputError
                                                    message={
                                                        formErrors.email_body
                                                    }
                                                />
                                            </div>

                                            <Button
                                                type="submit"
                                                disabled={
                                                    processing ||
                                                    !assessment.can_review
                                                }
                                            >
                                                {processing ? (
                                                    <Spinner />
                                                ) : (
                                                    <CheckCircle2 />
                                                )}
                                                Approve
                                            </Button>
                                        </>
                                    );
                                }}
                            </Form>

                            <RejectAssessmentDialog assessment={assessment} />
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}

function RecoveryActions({ assessment }: { assessment: Assessment }) {
    const hasRecoveryAction =
        assessment.can_retry ||
        assessment.can_retry_email ||
        assessment.can_promote ||
        assessment.can_override_score;

    if (!hasRecoveryAction) {
        return null;
    }

    return (
        <section className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 className="text-base font-medium">
                        Recovery and Override
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Handle failed evaluations, failed email delivery, false
                        negatives, and manual score corrections without touching
                        the database.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {assessment.can_retry && (
                        <RetryEvaluationDialog assessment={assessment} />
                    )}
                    {assessment.can_retry_email && (
                        <RetryEmailDialog assessment={assessment} />
                    )}
                    {assessment.can_promote && (
                        <PromoteAssessmentDialog assessment={assessment} />
                    )}
                    {assessment.can_override_score && (
                        <OverrideScoreDialog assessment={assessment} />
                    )}
                </div>
            </div>
        </section>
    );
}

function RetryEvaluationDialog({ assessment }: { assessment: Assessment }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <RotateCcw />
                    Retry Evaluation
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Retry evaluation</DialogTitle>
                <DialogDescription>
                    Queue a fresh AI evaluation job for this failed assessment.
                    The existing assessment record will be reused.
                </DialogDescription>

                <Form
                    {...AssessmentController.retryEvaluation.form(
                        assessment.id,
                    )}
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                >
                    {({ errors, processing }) => {
                        const formErrors = errors as Record<
                            string,
                            string | undefined
                        >;

                        return (
                            <>
                                <InputError message={formErrors.assessment} />

                                <div className="grid gap-2">
                                    <Label htmlFor="retry_reason">
                                        Reason (optional)
                                    </Label>
                                    <textarea
                                        id="retry_reason"
                                        name="reason"
                                        rows={4}
                                        placeholder="Example: Qwen timed out during the first evaluation."
                                        className="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                    />
                                    <InputError message={formErrors.reason} />
                                </div>

                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <RotateCcw />
                                        )}
                                        Queue Retry
                                    </Button>
                                </DialogFooter>
                            </>
                        );
                    }}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function RetryEmailDialog({ assessment }: { assessment: Assessment }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Mail />
                    Retry Email
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Retry interview email</DialogTitle>
                <DialogDescription>
                    Queue another delivery attempt using the approved subject
                    and body already stored on this assessment.
                </DialogDescription>

                <Form
                    {...AssessmentController.retryEmail.form(assessment.id)}
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                >
                    {({ errors, processing }) => {
                        const formErrors = errors as Record<
                            string,
                            string | undefined
                        >;

                        return (
                            <>
                                <InputError message={formErrors.assessment} />

                                <div className="grid gap-2">
                                    <Label htmlFor="retry_email_reason">
                                        Reason (optional)
                                    </Label>
                                    <textarea
                                        id="retry_email_reason"
                                        name="reason"
                                        rows={3}
                                        placeholder="Example: Resend after fixing mail transport."
                                        className="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                    />
                                    <InputError message={formErrors.reason} />
                                </div>

                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? <Spinner /> : <Mail />}
                                        Queue Email
                                    </Button>
                                </DialogFooter>
                            </>
                        );
                    }}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function PromoteAssessmentDialog({ assessment }: { assessment: Assessment }) {
    const hasExistingDraft = Boolean(
        assessment.ai_email_subject && assessment.ai_email_body,
    );

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <TrendingUp />
                    Promote
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Promote to interview review</DialogTitle>
                <DialogDescription>
                    Move this false negative into pending approval. If no AI
                    email draft exists, provide a manual draft here.
                </DialogDescription>

                <Form
                    {...AssessmentController.promote.form(assessment.id)}
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                >
                    {({ errors, processing }) => {
                        const formErrors = errors as Record<
                            string,
                            string | undefined
                        >;

                        return (
                            <>
                                <InputError message={formErrors.assessment} />

                                <div className="grid gap-2">
                                    <Label htmlFor="promote_reason">
                                        Reason
                                    </Label>
                                    <textarea
                                        id="promote_reason"
                                        name="reason"
                                        rows={4}
                                        placeholder="Explain why this candidate should continue despite the AI recommendation."
                                        className="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                    />
                                    <InputError message={formErrors.reason} />
                                </div>

                                <div className="rounded-md bg-muted/40 p-3 text-sm text-muted-foreground">
                                    {hasExistingDraft
                                        ? 'An AI email draft already exists. Leave the fields below blank to keep it, or fill both fields to replace it.'
                                        : 'No AI email draft exists. Fill both fields below so the promoted assessment can be approved later.'}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="promote_email_subject">
                                        Manual email subject
                                    </Label>
                                    <input
                                        id="promote_email_subject"
                                        name="email_subject"
                                        defaultValue=""
                                        className="flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                    />
                                    <InputError
                                        message={formErrors.email_subject}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="promote_email_body">
                                        Manual email body
                                    </Label>
                                    <textarea
                                        id="promote_email_body"
                                        name="email_body"
                                        rows={6}
                                        className="flex min-h-32 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                    />
                                    <InputError
                                        message={formErrors.email_body}
                                    />
                                </div>

                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <TrendingUp />
                                        )}
                                        Promote
                                    </Button>
                                </DialogFooter>
                            </>
                        );
                    }}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function OverrideScoreDialog({ assessment }: { assessment: Assessment }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <SlidersHorizontal />
                    Override Score
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Override ranking score</DialogTitle>
                <DialogDescription>
                    Replace the backend ranking score with an auditable manual
                    score and reason.
                </DialogDescription>

                <Form
                    {...AssessmentController.overrideScore.form(assessment.id)}
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                >
                    {({ errors, processing }) => {
                        const formErrors = errors as Record<
                            string,
                            string | undefined
                        >;

                        return (
                            <>
                                <InputError message={formErrors.assessment} />

                                <div className="grid gap-2">
                                    <Label htmlFor="ranking_score">
                                        Ranking score
                                    </Label>
                                    <input
                                        id="ranking_score"
                                        name="ranking_score"
                                        type="number"
                                        min="0"
                                        max="100"
                                        defaultValue={
                                            assessment.ranking_score ?? ''
                                        }
                                        className="flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                    />
                                    <InputError
                                        message={formErrors.ranking_score}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="override_reason">
                                        Reason
                                    </Label>
                                    <textarea
                                        id="override_reason"
                                        name="reason"
                                        rows={4}
                                        placeholder="Explain the manual evidence behind this score."
                                        className="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                    />
                                    <InputError message={formErrors.reason} />
                                </div>

                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <SlidersHorizontal />
                                        )}
                                        Save Override
                                    </Button>
                                </DialogFooter>
                            </>
                        );
                    }}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function RejectAssessmentDialog({ assessment }: { assessment: Assessment }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline" disabled={!assessment.can_review}>
                    <XCircle />
                    Reject
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Reject assessment</DialogTitle>
                <DialogDescription>
                    Reject this candidate without sending an email. A reason is
                    required for the audit trail.
                </DialogDescription>

                <Form
                    {...AssessmentController.reject.form(assessment.id)}
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                >
                    {({ errors, processing }) => {
                        const formErrors = errors as Record<
                            string,
                            string | undefined
                        >;

                        return (
                            <>
                                <InputError message={formErrors.assessment} />

                                <div className="grid gap-2">
                                    <Label htmlFor="reject_reason">
                                        Reason
                                    </Label>
                                    <textarea
                                        id="reject_reason"
                                        name="reason"
                                        rows={4}
                                        placeholder="Explain why this assessment is rejected."
                                        className="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                    />
                                    <InputError message={formErrors.reason} />
                                </div>

                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        {processing ? <Spinner /> : <XCircle />}
                                        Reject
                                    </Button>
                                </DialogFooter>
                            </>
                        );
                    }}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function ActivityTimeline({ events }: { events: AssessmentEvent[] }) {
    return (
        <section className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
            <div className="flex items-start gap-3">
                <Activity className="mt-0.5 h-5 w-5 text-muted-foreground" />
                <div>
                    <h2 className="text-base font-medium">
                        Agent Activity Timeline
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Step-by-step audit trail for candidate, AI, backend, and
                        Admin actions.
                    </p>
                </div>
            </div>

            {events.length === 0 ? (
                <p className="mt-5 rounded-md bg-muted/40 p-4 text-sm text-muted-foreground">
                    No timeline events have been recorded yet.
                </p>
            ) : (
                <ol className="mt-6 space-y-5">
                    {events.map((event) => (
                        <li key={event.id} className="relative pl-6">
                            <span className="absolute top-1.5 left-0 h-2.5 w-2.5 rounded-full bg-primary" />
                            <div className="space-y-2">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 className="text-sm font-medium">
                                            {event.title}
                                        </h3>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {event.type}
                                            {event.actor
                                                ? ` by ${event.actor.name ?? event.actor.email ?? 'Unknown actor'}`
                                                : ''}
                                        </p>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {event.occurred_at}
                                    </p>
                                </div>

                                {event.description && (
                                    <p className="text-sm text-muted-foreground">
                                        {event.description}
                                    </p>
                                )}

                                <EventPayload payload={event.payload} />
                            </div>
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}

function AuditPanel({ assessment }: { assessment: Assessment }) {
    const resumePayload = assessment.resume_payload;
    const criticPayload = assessment.critic_payload;

    return (
        <section className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
            <div className="flex items-start gap-3">
                <ShieldCheck className="mt-0.5 h-5 w-5 text-muted-foreground" />
                <div>
                    <h2 className="text-base font-medium">AI Audit Panel</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Runtime configuration and risk signals used for review.
                    </p>
                </div>
            </div>

            <div className="mt-5 space-y-4 text-sm">
                <AuditRow label="Provider" value={assessment.audit.provider} />
                <AuditRow label="Model" value={assessment.audit.model ?? '-'} />
                <AuditRow
                    label="Threshold"
                    value={
                        assessment.audit.threshold_source === 'campaign'
                            ? `${assessment.audit.threshold} (campaign)`
                            : `${assessment.audit.threshold} (global default ${assessment.audit.global_passing_score})`
                    }
                />
                <AuditRow
                    label="Ranking formula"
                    value={assessment.audit.ranking_formula}
                />
                {assessment.audit.override_score !== null && (
                    <AuditRow
                        label="Override score"
                        value={assessment.audit.override_score}
                    />
                )}
                {assessment.audit.override_reason && (
                    <AuditRow
                        label="Override reason"
                        value={assessment.audit.override_reason}
                    />
                )}
                <AuditRow
                    label="Needs manual review"
                    value={assessment.needs_manual_review ? 'Yes' : 'No'}
                />
                <AuditRow
                    label="Critic outcome"
                    value={criticPayload?.outcome ?? '-'}
                />
                <AuditRow
                    label="Critic findings"
                    value={criticPayload?.findings?.length ?? 0}
                />
                <AuditRow
                    label="Resume confidence"
                    value={resumePayload?.confidence ?? '-'}
                />
                <AuditRow
                    label="Risk flags"
                    value={resumePayload?.risk_flags?.length ?? 0}
                />
                <AuditRow
                    label="Interview probes"
                    value={resumePayload?.interview_probes?.length ?? 0}
                />
            </div>
        </section>
    );
}

function AuditRow({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="grid gap-1 border-b border-sidebar-border/50 pb-3 last:border-0 last:pb-0">
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className="font-medium break-words">{value}</p>
        </div>
    );
}

function EventPayload({
    payload,
}: {
    payload: Record<string, unknown> | null;
}) {
    if (!payload || Object.keys(payload).length === 0) {
        return null;
    }

    return (
        <dl className="grid gap-2 rounded-md bg-muted/40 p-3 text-xs sm:grid-cols-2">
            {Object.entries(payload).map(([key, value]) => (
                <div key={key} className="space-y-1">
                    <dt className="font-medium text-muted-foreground">
                        {formatPayloadKey(key)}
                    </dt>
                    <dd className="break-words">{formatPayloadValue(value)}</dd>
                </div>
            ))}
        </dl>
    );
}

function ResumeScreeningList({
    title,
    items,
}: {
    title: string;
    items: string[];
}) {
    if (items.length === 0) {
        return null;
    }

    return (
        <div>
            <p className="text-xs font-medium text-muted-foreground">{title}</p>
            <ul className="mt-2 list-inside list-disc space-y-1 text-sm">
                {items.map((item) => (
                    <li key={item}>{item}</li>
                ))}
            </ul>
        </div>
    );
}

function formatPayloadKey(key: string): string {
    return key.replaceAll('_', ' ');
}

function formatPayloadValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '-';
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    if (Array.isArray(value)) {
        return value.map(formatPayloadValue).join(', ');
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function ScoreComponent({
    label,
    score,
    configuredWeight,
    normalizedWeight,
}: {
    label: string;
    score?: number | null;
    configuredWeight?: number;
    normalizedWeight?: number;
}) {
    return (
        <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-sm font-medium">{label}</p>
                    <p className="mt-1 text-2xl font-semibold">
                        {score ?? '-'}
                    </p>
                </div>
                <div className="text-right text-xs text-muted-foreground">
                    <p>Configured {configuredWeight ?? '-'}%</p>
                    <p>Used {normalizedWeight ?? '-'}%</p>
                </div>
            </div>
        </div>
    );
}

AdminAssessmentsShow.layout = {
    breadcrumbs: [
        {
            title: 'Assessment Workstation',
            href: admin.assessments.index(),
        },
        {
            title: 'Review',
            href: admin.assessments.index(),
        },
    ],
};
