import { Form, Head, usePoll } from '@inertiajs/react';
import {
    CheckCircle2,
    Ellipsis,
    FileText,
    Inbox,
    Mail,
    Medal,
    RotateCcw,
    ShieldCheck,
    SlidersHorizontal,
    TrendingUp,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { Bar, ComposedChart, LabelList, Scatter, XAxis, YAxis } from 'recharts';
import AssessmentController from '@/actions/App/Http/Controllers/Admin/AssessmentController';
import AssessmentStatusBadge from '@/components/assessment-status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogMedia,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
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
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import admin from '@/routes/admin';

type AnswerSnapshot = {
    question_id: number;
    section_id?: number | null;
    section_title?: string | null;
    question: string;
    rubric: string;
    answer: string;
};

type SectionScore = {
    section_id?: number | null;
    title: string;
    weight: number;
    earned_points: number;
    total_points: number;
    score: number | null;
};

type QuestionEvaluation = {
    question_id: number;
    score: number;
    confidence: number;
    points: number;
    earned_points: number;
    justification: string;
};

type EvaluationPayload = {
    score: number;
    confidence: number;
    justification: string;
    question_evaluations: QuestionEvaluation[];
    section_scores: SectionScore[];
    manual_review_reasons?: string[];
};

type Assessment = {
    id: number;
    candidate: {
        name: string | null;
        email: string | null;
    };
    campaign: {
        title: string | null;
        role_title: string | null;
    };
    rank: number | null;
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
    assessment_score: number | null;
    evaluation_payload: EvaluationPayload | null;
    ranking_score: number | null;
    section_scores: SectionScore[];
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
};

type Props = {
    assessment: Assessment;
};

type AnswerSection = {
    key: string;
    title: string;
    answers: AnswerSnapshot[];
};

const PROCESSING_STATUSES = [
    'submitted',
    'resume_processing',
    'resume_screening',
    'evaluating',
];

const sectionScoreChartConfig = {
    score: {
        label: 'Score',
        color: 'var(--chart-1)',
    },
} satisfies ChartConfig;

function candidateInitials(name: string | null, email: string | null) {
    if (name) {
        return name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0]?.toUpperCase() ?? '')
            .join('');
    }

    return email?.[0]?.toUpperCase() ?? '?';
}

function formatDate(value: string | null) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString();
}

function scoreOrDash(score: number | null | undefined) {
    return score ?? '-';
}

function groupAnswersBySection(answers: AnswerSnapshot[]): AnswerSection[] {
    const sections = new Map<string, AnswerSection>();

    answers.forEach((answer) => {
        const title = answer.section_title?.trim() || 'General';
        const key =
            answer.section_id !== null && answer.section_id !== undefined
                ? `section-${answer.section_id}`
                : `section-${title}`;
        const section = sections.get(key);

        if (section) {
            section.answers.push(answer);

            return;
        }

        sections.set(key, {
            key,
            title,
            answers: [answer],
        });
    });

    return Array.from(sections.values());
}

export default function AdminAssessmentsShow({ assessment }: Props) {
    const subject =
        assessment.approved_email_subject ??
        assessment.ai_email_subject ??
        'Interview Invitation';
    const body =
        assessment.approved_email_body ?? assessment.ai_email_body ?? '';
    const candidateName =
        assessment.candidate.name ??
        assessment.candidate.email ??
        'Unknown candidate';
    const assessmentScore = assessment.assessment_score;
    const isProcessing = PROCESSING_STATUSES.includes(assessment.status);
    const { start, stop } = usePoll(
        2000,
        { only: ['assessment'] },
        { autoStart: false, mode: 'rest' },
    );

    useEffect(() => {
        if (isProcessing) {
            start();

            return;
        }

        stop();
    }, [isProcessing, start, stop]);

    return (
        <>
            <Head title="Assessment Review" />

            <div className="flex flex-col gap-6 p-4">
                <AssessmentOverviewCard
                    assessment={assessment}
                    candidateName={candidateName}
                    subject={subject}
                    body={body}
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <ScoreMetric
                        label="Ranking score"
                        score={assessment.ranking_score}
                        isProcessing={isProcessing}
                    />
                    <ScoreMetric
                        label="Resume"
                        score={assessment.resume_score}
                        isProcessing={isProcessing}
                    />
                    <ScoreMetric
                        label="Assessment"
                        score={assessmentScore}
                        isProcessing={isProcessing}
                    />
                    <ScoreMetric
                        label="AI confidence"
                        score={assessment.evaluation_payload?.confidence}
                        isProcessing={isProcessing}
                    />
                </div>

                {assessment.needs_manual_review ? (
                    <Alert>
                        <ShieldCheck />
                        <AlertTitle>Manual review required</AlertTitle>
                        <AlertDescription>
                            This submission was flagged for human review before
                            final approval or rejection.
                            {assessment.evaluation_payload
                                ?.manual_review_reasons?.length ? (
                                <span className="mt-1 block">
                                    Reasons:{' '}
                                    {assessment.evaluation_payload.manual_review_reasons
                                        .map((reason) =>
                                            reason.replaceAll('_', ' '),
                                        )
                                        .join(', ')}
                                </span>
                            ) : null}
                        </AlertDescription>
                    </Alert>
                ) : null}

                <EvaluationSummary assessment={assessment} />

                <SectionPerformance
                    assessment={assessment}
                    isProcessing={isProcessing}
                />

                <Tabs defaultValue="resume" className="gap-4">
                    <TabsList variant="line" className="w-full justify-start">
                        <TabsTrigger value="resume">Resume</TabsTrigger>
                        <TabsTrigger value="answers">Answers</TabsTrigger>
                    </TabsList>

                    <TabsContent value="resume" className="flex flex-col gap-6">
                        <ResumeTab assessment={assessment} />
                    </TabsContent>

                    <TabsContent
                        value="answers"
                        className="flex flex-col gap-6"
                    >
                        <AnswersTab assessment={assessment} />
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

function AssessmentOverviewCard({
    assessment,
    candidateName,
    subject,
    body,
}: {
    assessment: Assessment;
    candidateName: string;
    subject: string;
    body: string;
}) {
    return (
        <Card>
            <CardHeader>
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex min-w-0 items-start gap-3">
                        <Avatar size="lg">
                            <AvatarFallback>
                                {candidateInitials(
                                    assessment.candidate.name,
                                    assessment.candidate.email,
                                )}
                            </AvatarFallback>
                        </Avatar>
                        <div className="flex min-w-0 flex-col gap-1.5">
                            <div className="flex flex-wrap items-center gap-2">
                                <CardTitle className="truncate">
                                    {candidateName}
                                </CardTitle>
                                {assessment.rank !== null ? (
                                    <Badge
                                        variant={
                                            assessment.rank <= 3
                                                ? assessment.rank === 1
                                                    ? 'default'
                                                    : 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        <Medal data-icon="inline-start" />#
                                        {assessment.rank}
                                    </Badge>
                                ) : null}
                                <AssessmentStatusBadge
                                    status={assessment.status}
                                />
                            </div>
                            <CardDescription className="truncate">
                                {assessment.candidate.email ?? '-'}
                            </CardDescription>
                            <p className="truncate text-sm text-muted-foreground">
                                {assessment.campaign.role_title ?? 'Role'} ·{' '}
                                {assessment.campaign.title ?? 'Campaign'}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Submitted {formatDate(assessment.created_at)}
                            </p>
                        </div>
                    </div>

                    <AssessmentReviewActions
                        assessment={assessment}
                        subject={subject}
                        body={body}
                    />
                </div>
            </CardHeader>
        </Card>
    );
}

function EvaluationSummary({ assessment }: { assessment: Assessment }) {
    if (!assessment.ai_justification) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>AI Evaluation Summary</CardTitle>
                <CardDescription>
                    The primary recommendation for this candidate review.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                    {assessment.ai_justification}
                </p>
            </CardContent>
        </Card>
    );
}

function SectionPerformance({
    assessment,
    isProcessing,
}: {
    assessment: Assessment;
    isProcessing: boolean;
}) {
    const chartData = assessment.section_scores.flatMap((section) =>
        section.score === null
            ? []
            : [
                  {
                      section: section.title,
                      score: section.score,
                      points: `${section.earned_points}/${section.total_points}`,
                  },
              ],
    );

    return (
        <Card>
            <CardHeader>
                <CardTitle>Section Performance</CardTitle>
                <CardDescription>
                    A quick comparison of the candidate's assessment results.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {isProcessing && chartData.length === 0 ? (
                    <div className="flex flex-col gap-4" aria-hidden="true">
                        <Skeleton className="h-8 w-3/4" />
                        <Skeleton className="h-8 w-2/3" />
                        <Skeleton className="h-8 w-4/5" />
                    </div>
                ) : chartData.length === 0 ? (
                    <Empty className="border border-dashed">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Medal />
                            </EmptyMedia>
                            <EmptyTitle>No section scores yet</EmptyTitle>
                            <EmptyDescription>
                                Section scores appear after assessment
                                evaluation completes.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <ChartContainer
                        config={sectionScoreChartConfig}
                        className="aspect-auto w-full"
                        style={{
                            height: Math.max(180, chartData.length * 44),
                        }}
                    >
                        <ComposedChart
                            accessibilityLayer
                            data={chartData}
                            layout="vertical"
                            margin={{ left: 8, right: 48 }}
                        >
                            <XAxis type="number" domain={[0, 100]} hide />
                            <YAxis
                                dataKey="section"
                                type="category"
                                tickLine={false}
                                axisLine={false}
                                width={152}
                                tickFormatter={(value: string) =>
                                    value.length > 24
                                        ? `${value.slice(0, 22)}…`
                                        : value
                                }
                            />
                            <ChartTooltip
                                cursor={false}
                                content={
                                    <ChartTooltipContent
                                        hideLabel
                                        hideIndicator
                                        formatter={(_value, _name, item) => (
                                            <div className="flex min-w-44 flex-1 flex-col gap-1">
                                                <span className="mb-1 font-medium">
                                                    {item.payload.section}
                                                </span>
                                                <div className="flex justify-between gap-4">
                                                    <span className="text-muted-foreground">
                                                        Score
                                                    </span>
                                                    <span className="font-mono font-medium tabular-nums">
                                                        {item.payload.score}%
                                                    </span>
                                                </div>
                                                <div className="flex justify-between gap-4">
                                                    <span className="text-muted-foreground">
                                                        Points
                                                    </span>
                                                    <span className="font-mono font-medium tabular-nums">
                                                        {item.payload.points}
                                                    </span>
                                                </div>
                                            </div>
                                        )}
                                    />
                                }
                            />
                            <Bar
                                dataKey="score"
                                fill="var(--color-score)"
                                fillOpacity={0.28}
                                barSize={4}
                                radius={999}
                                background={{
                                    fill: 'var(--muted)',
                                    radius: 999,
                                }}
                            >
                                <LabelList
                                    dataKey="score"
                                    position="right"
                                    formatter={(value) => `${String(value)}%`}
                                    className="fill-foreground"
                                    fontSize={12}
                                />
                            </Bar>
                            <Scatter
                                dataKey="score"
                                fill="var(--color-score)"
                                tooltipType="none"
                            />
                        </ComposedChart>
                    </ChartContainer>
                )}
            </CardContent>
        </Card>
    );
}

function ResumeTab({ assessment }: { assessment: Assessment }) {
    const summary =
        assessment.resume_payload?.summary ??
        assessment.resume_justification ??
        null;

    return (
        <div className="flex flex-col gap-6">
            <Card>
                <CardHeader>
                    <CardTitle>Resume Screening</CardTitle>
                    <CardDescription className="flex flex-wrap gap-x-3 gap-y-1">
                        <span className="truncate">
                            {assessment.resume_original_name ?? 'No file'}
                        </span>
                        <span>
                            Score {scoreOrDash(assessment.resume_score)}
                        </span>
                        <span>
                            Confidence{' '}
                            {assessment.resume_payload?.confidence ?? '-'}
                        </span>
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {summary ? (
                        <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                            {summary}
                        </p>
                    ) : (
                        <Empty className="border border-dashed">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <FileText />
                                </EmptyMedia>
                                <EmptyTitle>No resume screening yet</EmptyTitle>
                                <EmptyDescription>
                                    Screening results appear after resume
                                    evaluation completes.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    )}
                </CardContent>
            </Card>

            <div className="grid gap-4 md:grid-cols-2">
                <SkillGroup
                    title="Matched skills"
                    items={assessment.resume_payload?.matched_skills ?? []}
                    variant="default"
                />
                <SkillGroup
                    title="Missing skills"
                    items={assessment.resume_payload?.missing_skills ?? []}
                    variant="outline"
                />
                <SkillGroup
                    title="Risk flags"
                    items={assessment.resume_payload?.risk_flags ?? []}
                    variant="destructive"
                />
                <SkillGroup
                    title="Interview probes"
                    items={assessment.resume_payload?.interview_probes ?? []}
                    variant="secondary"
                />
            </div>
        </div>
    );
}

function AnswersTab({ assessment }: { assessment: Assessment }) {
    if (assessment.answers_payload.length === 0) {
        return (
            <Empty className="border border-dashed">
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <Inbox />
                    </EmptyMedia>
                    <EmptyTitle>No answers recorded</EmptyTitle>
                    <EmptyDescription>
                        Answer snapshots appear after the candidate submits the
                        assessment.
                    </EmptyDescription>
                </EmptyHeader>
            </Empty>
        );
    }

    const answerSections = groupAnswersBySection(assessment.answers_payload);

    return (
        <ScrollArea className="h-[70vh] rounded-lg">
            <div className="flex flex-col gap-8 pr-4">
                {answerSections.map((section) => (
                    <section key={section.key} className="flex flex-col gap-4">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h3 className="font-medium">{section.title}</h3>
                            <Badge variant="secondary">
                                {section.answers.length}{' '}
                                {section.answers.length === 1
                                    ? 'question'
                                    : 'questions'}
                            </Badge>
                        </div>

                        {section.answers.map((answer, index) => {
                            const evaluation =
                                assessment.evaluation_payload?.question_evaluations.find(
                                    (item) =>
                                        item.question_id === answer.question_id,
                                );

                            return (
                                <Card key={answer.question_id}>
                                    <CardHeader>
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <CardDescription>
                                                Question {index + 1}
                                            </CardDescription>
                                            {evaluation ? (
                                                <div className="flex flex-wrap gap-2">
                                                    <Badge variant="secondary">
                                                        Score {evaluation.score}
                                                    </Badge>
                                                    <Badge variant="outline">
                                                        Confidence{' '}
                                                        {evaluation.confidence}%
                                                    </Badge>
                                                    <Badge variant="outline">
                                                        {
                                                            evaluation.earned_points
                                                        }
                                                        /{evaluation.points} pts
                                                    </Badge>
                                                </div>
                                            ) : null}
                                        </div>
                                        <CardTitle>{answer.question}</CardTitle>
                                    </CardHeader>
                                    <CardContent className="flex flex-col gap-4">
                                        <Field>
                                            <FieldLabel>Rubric</FieldLabel>
                                            <FieldContent>
                                                <p className="text-sm whitespace-pre-wrap">
                                                    {answer.rubric}
                                                </p>
                                            </FieldContent>
                                        </Field>
                                        <Separator />
                                        <Field>
                                            <FieldLabel>Answer</FieldLabel>
                                            <FieldContent>
                                                <p className="text-sm whitespace-pre-wrap">
                                                    {answer.answer}
                                                </p>
                                            </FieldContent>
                                        </Field>
                                        {evaluation ? (
                                            <>
                                                <Separator />
                                                <Field>
                                                    <FieldLabel>
                                                        AI evaluation
                                                    </FieldLabel>
                                                    <FieldContent>
                                                        <p className="text-sm whitespace-pre-wrap">
                                                            {
                                                                evaluation.justification
                                                            }
                                                        </p>
                                                    </FieldContent>
                                                </Field>
                                            </>
                                        ) : null}
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </section>
                ))}
            </div>
        </ScrollArea>
    );
}

function InterviewEmailDialog({
    assessment,
    subject,
    body,
    open,
    onOpenChange,
}: {
    assessment: Assessment;
    subject: string;
    body: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Interview Email</DialogTitle>
                    <DialogDescription>
                        Review and approve the final invitation draft.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...AssessmentController.approve.form(assessment.id)}
                    className="flex flex-col gap-4"
                >
                    {({ errors, processing }) => {
                        const formErrors = formErrorsFrom(errors);
                        const hasSubjectError = Boolean(
                            formErrors.email_subject,
                        );
                        const hasBodyError = Boolean(formErrors.email_body);

                        return (
                            <>
                                <FieldGroup>
                                    {formErrors.assessment ? (
                                        <Field>
                                            <FieldError>
                                                {formErrors.assessment}
                                            </FieldError>
                                        </Field>
                                    ) : null}

                                    {!assessment.can_review ? (
                                        <Field>
                                            <FieldDescription>
                                                This assessment is not open for
                                                review.
                                            </FieldDescription>
                                        </Field>
                                    ) : null}

                                    <Field
                                        data-invalid={
                                            hasSubjectError || undefined
                                        }
                                        data-disabled={
                                            !assessment.can_review || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="email_subject">
                                            Subject
                                        </FieldLabel>
                                        <Input
                                            id="email_subject"
                                            name="email_subject"
                                            defaultValue={subject}
                                            disabled={!assessment.can_review}
                                            aria-invalid={
                                                hasSubjectError || undefined
                                            }
                                        />
                                        <FieldError
                                            errors={[
                                                {
                                                    message:
                                                        formErrors.email_subject,
                                                },
                                            ]}
                                        />
                                    </Field>

                                    <Field
                                        data-invalid={hasBodyError || undefined}
                                        data-disabled={
                                            !assessment.can_review || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="email_body">
                                            Body
                                        </FieldLabel>
                                        <Textarea
                                            id="email_body"
                                            name="email_body"
                                            rows={12}
                                            defaultValue={body}
                                            disabled={!assessment.can_review}
                                            aria-invalid={
                                                hasBodyError || undefined
                                            }
                                            className="min-h-56"
                                        />
                                        <FieldError
                                            errors={[
                                                {
                                                    message:
                                                        formErrors.email_body,
                                                },
                                            ]}
                                        />
                                    </Field>
                                </FieldGroup>

                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing || !assessment.can_review
                                        }
                                    >
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <CheckCircle2 data-icon="inline-start" />
                                        )}
                                        Approve
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

function ScoreMetric({
    label,
    score,
    isProcessing = false,
}: {
    label: string;
    score: number | null | undefined;
    isProcessing?: boolean;
}) {
    const isPending = isProcessing && typeof score !== 'number';

    return (
        <Card size="sm">
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle>
                    {isPending ? (
                        <Skeleton
                            className="h-7 w-16"
                            aria-label={`${label} is being calculated`}
                        />
                    ) : (
                        <span className="text-2xl tabular-nums">
                            {scoreOrDash(score)}
                        </span>
                    )}
                </CardTitle>
            </CardHeader>
        </Card>
    );
}

function SkillGroup({
    title,
    items,
    variant,
}: {
    title: string;
    items: string[];
    variant: 'default' | 'secondary' | 'destructive' | 'outline';
}) {
    return (
        <Card size="sm">
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>
                    {items.length} item{items.length === 1 ? '' : 's'}
                </CardDescription>
            </CardHeader>
            <CardContent>
                {items.length === 0 ? (
                    <Empty className="border border-dashed p-6">
                        <EmptyHeader>
                            <EmptyTitle>None</EmptyTitle>
                            <EmptyDescription>
                                No {title.toLowerCase()} recorded.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <div className="flex flex-wrap gap-2">
                        {items.map((item) => (
                            <Badge key={item} variant={variant}>
                                {item}
                            </Badge>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

type AssessmentAction =
    | 'email'
    | 'retry'
    | 'retry_email'
    | 'promote'
    | 'override_score';

function AssessmentReviewActions({
    assessment,
    subject,
    body,
}: {
    assessment: Assessment;
    subject: string;
    body: string;
}) {
    const [openAction, setOpenAction] = useState<AssessmentAction | null>(null);

    const hasRecoveryAction =
        assessment.can_retry ||
        assessment.can_retry_email ||
        assessment.can_promote ||
        assessment.can_override_score;

    return (
        <>
            <div className="flex flex-wrap items-center justify-end gap-2">
                {assessment.can_review ? (
                    <>
                        <RejectAssessmentDialog assessment={assessment} />
                        <Button onClick={() => setOpenAction('email')}>
                            Review & Approve
                        </Button>
                    </>
                ) : null}

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="More assessment actions"
                        >
                            <Ellipsis />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="min-w-48">
                        {!assessment.can_review ? (
                            <DropdownMenuGroup>
                                <DropdownMenuItem
                                    onSelect={() => setOpenAction('email')}
                                >
                                    <Mail />
                                    View Interview Email
                                </DropdownMenuItem>
                            </DropdownMenuGroup>
                        ) : null}

                        {!assessment.can_review && hasRecoveryAction ? (
                            <DropdownMenuSeparator />
                        ) : null}

                        {hasRecoveryAction ? (
                            <DropdownMenuGroup>
                                {assessment.can_retry ? (
                                    <DropdownMenuItem
                                        onSelect={() => setOpenAction('retry')}
                                    >
                                        <RotateCcw />
                                        Retry Evaluation
                                    </DropdownMenuItem>
                                ) : null}
                                {assessment.can_retry_email ? (
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            setOpenAction('retry_email')
                                        }
                                    >
                                        <Mail />
                                        Retry Email
                                    </DropdownMenuItem>
                                ) : null}
                                {assessment.can_promote ? (
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            setOpenAction('promote')
                                        }
                                    >
                                        <TrendingUp />
                                        Promote
                                    </DropdownMenuItem>
                                ) : null}
                                {assessment.can_override_score ? (
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            setOpenAction('override_score')
                                        }
                                    >
                                        <SlidersHorizontal />
                                        Override Score
                                    </DropdownMenuItem>
                                ) : null}
                            </DropdownMenuGroup>
                        ) : null}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <InterviewEmailDialog
                assessment={assessment}
                subject={subject}
                body={body}
                open={openAction === 'email'}
                onOpenChange={(open) => setOpenAction(open ? 'email' : null)}
            />
            <RetryEvaluationDialog
                assessment={assessment}
                open={openAction === 'retry'}
                onOpenChange={(open) => setOpenAction(open ? 'retry' : null)}
            />
            <RetryEmailDialog
                assessment={assessment}
                open={openAction === 'retry_email'}
                onOpenChange={(open) =>
                    setOpenAction(open ? 'retry_email' : null)
                }
            />
            <PromoteAssessmentDialog
                assessment={assessment}
                open={openAction === 'promote'}
                onOpenChange={(open) => setOpenAction(open ? 'promote' : null)}
            />
            <OverrideScoreDialog
                assessment={assessment}
                open={openAction === 'override_score'}
                onOpenChange={(open) =>
                    setOpenAction(open ? 'override_score' : null)
                }
            />
        </>
    );
}

function formErrorsFrom(errors: unknown): Record<string, string | undefined> {
    return errors as Record<string, string | undefined>;
}

type ControlledDialogProps = {
    assessment: Assessment;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function RetryEvaluationDialog({
    assessment,
    open,
    onOpenChange,
}: ControlledDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Retry evaluation</DialogTitle>
                    <DialogDescription>
                        Queue a fresh AI evaluation job for this failed
                        assessment. The existing assessment record will be
                        reused.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...AssessmentController.retryEvaluation.form(
                        assessment.id,
                    )}
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-4"
                >
                    {({ errors, processing }) => {
                        const formErrors = formErrorsFrom(errors);
                        const hasReasonError = Boolean(formErrors.reason);

                        return (
                            <>
                                <FieldGroup>
                                    {formErrors.assessment ? (
                                        <Field>
                                            <FieldError>
                                                {formErrors.assessment}
                                            </FieldError>
                                        </Field>
                                    ) : null}

                                    <Field
                                        data-invalid={
                                            hasReasonError || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="retry_reason">
                                            Reason (optional)
                                        </FieldLabel>
                                        <Textarea
                                            id="retry_reason"
                                            name="reason"
                                            rows={4}
                                            placeholder="Example: Qwen timed out during the first evaluation."
                                            aria-invalid={
                                                hasReasonError || undefined
                                            }
                                        />
                                        <FieldError
                                            errors={[
                                                {
                                                    message: formErrors.reason,
                                                },
                                            ]}
                                        />
                                    </Field>
                                </FieldGroup>

                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <RotateCcw data-icon="inline-start" />
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

function RetryEmailDialog({
    assessment,
    open,
    onOpenChange,
}: ControlledDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Retry interview email</DialogTitle>
                    <DialogDescription>
                        Queue another delivery attempt using the approved
                        subject and body already stored on this assessment.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...AssessmentController.retryEmail.form(assessment.id)}
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-4"
                >
                    {({ errors, processing }) => {
                        const formErrors = formErrorsFrom(errors);
                        const hasReasonError = Boolean(formErrors.reason);

                        return (
                            <>
                                <FieldGroup>
                                    {formErrors.assessment ? (
                                        <Field>
                                            <FieldError>
                                                {formErrors.assessment}
                                            </FieldError>
                                        </Field>
                                    ) : null}

                                    <Field
                                        data-invalid={
                                            hasReasonError || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="retry_email_reason">
                                            Reason (optional)
                                        </FieldLabel>
                                        <Textarea
                                            id="retry_email_reason"
                                            name="reason"
                                            rows={3}
                                            placeholder="Example: Resend after fixing mail transport."
                                            aria-invalid={
                                                hasReasonError || undefined
                                            }
                                        />
                                        <FieldError
                                            errors={[
                                                {
                                                    message: formErrors.reason,
                                                },
                                            ]}
                                        />
                                    </Field>
                                </FieldGroup>

                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <Mail data-icon="inline-start" />
                                        )}
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

function PromoteAssessmentDialog({
    assessment,
    open,
    onOpenChange,
}: ControlledDialogProps) {
    const hasExistingDraft = Boolean(
        assessment.ai_email_subject && assessment.ai_email_body,
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Promote to interview review</DialogTitle>
                    <DialogDescription>
                        Move this false negative into pending approval. If no AI
                        email draft exists, provide a manual draft here.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...AssessmentController.promote.form(assessment.id)}
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-4"
                >
                    {({ errors, processing }) => {
                        const formErrors = formErrorsFrom(errors);
                        const hasReasonError = Boolean(formErrors.reason);
                        const hasSubjectError = Boolean(
                            formErrors.email_subject,
                        );
                        const hasBodyError = Boolean(formErrors.email_body);

                        return (
                            <>
                                <FieldGroup>
                                    {formErrors.assessment ? (
                                        <Field>
                                            <FieldError>
                                                {formErrors.assessment}
                                            </FieldError>
                                        </Field>
                                    ) : null}

                                    <Field
                                        data-invalid={
                                            hasReasonError || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="promote_reason">
                                            Reason
                                        </FieldLabel>
                                        <Textarea
                                            id="promote_reason"
                                            name="reason"
                                            rows={4}
                                            placeholder="Explain why this candidate should continue despite the AI recommendation."
                                            aria-invalid={
                                                hasReasonError || undefined
                                            }
                                        />
                                        <FieldError
                                            errors={[
                                                {
                                                    message: formErrors.reason,
                                                },
                                            ]}
                                        />
                                    </Field>

                                    <Alert>
                                        <Mail />
                                        <AlertTitle>Email draft</AlertTitle>
                                        <AlertDescription>
                                            {hasExistingDraft
                                                ? 'An AI email draft already exists. Leave the fields below blank to keep it, or fill both fields to replace it.'
                                                : 'No AI email draft exists. Fill both fields below so the promoted assessment can be approved later.'}
                                        </AlertDescription>
                                    </Alert>

                                    <Field
                                        data-invalid={
                                            hasSubjectError || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="promote_email_subject">
                                            Manual email subject
                                        </FieldLabel>
                                        <Input
                                            id="promote_email_subject"
                                            name="email_subject"
                                            defaultValue=""
                                            aria-invalid={
                                                hasSubjectError || undefined
                                            }
                                        />
                                        <FieldError
                                            errors={[
                                                {
                                                    message:
                                                        formErrors.email_subject,
                                                },
                                            ]}
                                        />
                                    </Field>

                                    <Field
                                        data-invalid={hasBodyError || undefined}
                                    >
                                        <FieldLabel htmlFor="promote_email_body">
                                            Manual email body
                                        </FieldLabel>
                                        <Textarea
                                            id="promote_email_body"
                                            name="email_body"
                                            rows={6}
                                            aria-invalid={
                                                hasBodyError || undefined
                                            }
                                        />
                                        <FieldError
                                            errors={[
                                                {
                                                    message:
                                                        formErrors.email_body,
                                                },
                                            ]}
                                        />
                                    </Field>
                                </FieldGroup>

                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <TrendingUp data-icon="inline-start" />
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

function OverrideScoreDialog({
    assessment,
    open,
    onOpenChange,
}: ControlledDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Override ranking score</DialogTitle>
                    <DialogDescription>
                        Replace the backend ranking score with an auditable
                        manual score and reason.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...AssessmentController.overrideScore.form(assessment.id)}
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-4"
                >
                    {({ errors, processing }) => {
                        const formErrors = formErrorsFrom(errors);
                        const hasScoreError = Boolean(formErrors.ranking_score);
                        const hasReasonError = Boolean(formErrors.reason);

                        return (
                            <>
                                <FieldGroup>
                                    {formErrors.assessment ? (
                                        <Field>
                                            <FieldError>
                                                {formErrors.assessment}
                                            </FieldError>
                                        </Field>
                                    ) : null}

                                    <Field
                                        data-invalid={
                                            hasScoreError || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="ranking_score">
                                            Ranking score
                                        </FieldLabel>
                                        <Input
                                            id="ranking_score"
                                            name="ranking_score"
                                            type="number"
                                            min="0"
                                            max="100"
                                            defaultValue={
                                                assessment.ranking_score ?? ''
                                            }
                                            aria-invalid={
                                                hasScoreError || undefined
                                            }
                                        />
                                        <FieldError
                                            errors={[
                                                {
                                                    message:
                                                        formErrors.ranking_score,
                                                },
                                            ]}
                                        />
                                    </Field>

                                    <Field
                                        data-invalid={
                                            hasReasonError || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="override_reason">
                                            Reason
                                        </FieldLabel>
                                        <Textarea
                                            id="override_reason"
                                            name="reason"
                                            rows={4}
                                            placeholder="Explain the manual evidence behind this score."
                                            aria-invalid={
                                                hasReasonError || undefined
                                            }
                                        />
                                        <FieldError
                                            errors={[
                                                {
                                                    message: formErrors.reason,
                                                },
                                            ]}
                                        />
                                    </Field>
                                </FieldGroup>

                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <SlidersHorizontal data-icon="inline-start" />
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
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <Button variant="outline" disabled={!assessment.can_review}>
                    Reject
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogMedia className="bg-destructive/10 text-destructive dark:bg-destructive/20 dark:text-destructive">
                        <XCircle />
                    </AlertDialogMedia>
                    <AlertDialogTitle>Reject assessment</AlertDialogTitle>
                    <AlertDialogDescription>
                        Reject this candidate without sending an email. A reason
                        is required for the audit trail.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <Form
                    {...AssessmentController.reject.form(assessment.id)}
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-4"
                >
                    {({ errors, processing }) => {
                        const formErrors = formErrorsFrom(errors);
                        const hasReasonError = Boolean(formErrors.reason);

                        return (
                            <>
                                <FieldGroup>
                                    {formErrors.assessment ? (
                                        <Field>
                                            <FieldError>
                                                {formErrors.assessment}
                                            </FieldError>
                                        </Field>
                                    ) : null}

                                    <Field
                                        data-invalid={
                                            hasReasonError || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="reject_reason">
                                            Reason
                                        </FieldLabel>
                                        <Textarea
                                            id="reject_reason"
                                            name="reason"
                                            rows={4}
                                            placeholder="Explain why this assessment is rejected."
                                            aria-invalid={
                                                hasReasonError || undefined
                                            }
                                        />
                                        <FieldError
                                            errors={[
                                                {
                                                    message: formErrors.reason,
                                                },
                                            ]}
                                        />
                                    </Field>
                                </FieldGroup>

                                <AlertDialogFooter>
                                    <AlertDialogCancel>
                                        Cancel
                                    </AlertDialogCancel>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <XCircle data-icon="inline-start" />
                                        )}
                                        Reject
                                    </Button>
                                </AlertDialogFooter>
                            </>
                        );
                    }}
                </Form>
            </AlertDialogContent>
        </AlertDialog>
    );
}

AdminAssessmentsShow.layout = {
    breadcrumbs: [
        {
            title: 'Rankings',
            href: admin.rankings.index(),
        },
        {
            title: 'Review',
            href: admin.rankings.index(),
        },
    ],
};
