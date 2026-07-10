import { Form, Head } from '@inertiajs/react';
import {
    CheckCircle2,
    EllipsisVertical,
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
import { useState } from 'react';
import AssessmentController from '@/actions/App/Http/Controllers/Admin/AssessmentController';
import AssessmentStatusBadge from '@/components/assessment-status-badge';
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
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { Progress } from '@/components/ui/progress';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import admin from '@/routes/admin';

type AnswerSnapshot = {
    question_id: number;
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
    section_scores?: SectionScore[];
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
    ai_score: number | null;
    mcq_score: number | null;
    essay_score: number | null;
    ranking_score: number | null;
    ranking_payload: RankingPayload | null;
    section_scores: SectionScore[];
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
};

type Props = {
    assessment: Assessment;
};

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
    const essayScore = assessment.essay_score ?? assessment.ai_score;

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
                    />
                    <ScoreMetric
                        label="Resume"
                        score={assessment.resume_score}
                    />
                    <ScoreMetric label="Essay" score={essayScore} />
                    <ScoreMetric
                        label="MCQ"
                        score={assessment.mcq_score}
                    />
                </div>

                {assessment.needs_manual_review ? (
                    <Alert>
                        <ShieldCheck />
                        <AlertTitle>Manual review required</AlertTitle>
                        <AlertDescription>
                            This submission was flagged for human review before
                            final approval or rejection.
                        </AlertDescription>
                    </Alert>
                ) : null}

                {assessment.ranking_payload?.missing_components &&
                assessment.ranking_payload.missing_components.length > 0 ? (
                    <Alert>
                        <SlidersHorizontal />
                        <AlertTitle>Ranking components missing</AlertTitle>
                        <AlertDescription>
                            Missing components:{' '}
                            {assessment.ranking_payload.missing_components.join(
                                ', ',
                            )}
                        </AlertDescription>
                    </Alert>
                ) : null}

                <Tabs defaultValue="overview" className="gap-4">
                    <TabsList variant="line" className="w-full justify-start">
                        <TabsTrigger value="overview">Overview</TabsTrigger>
                        <TabsTrigger value="resume">Resume</TabsTrigger>
                        <TabsTrigger value="answers">Answers</TabsTrigger>
                    </TabsList>

                    <TabsContent value="overview" className="flex flex-col gap-6">
                        <OverviewTab assessment={assessment} />
                    </TabsContent>

                    <TabsContent value="resume" className="flex flex-col gap-6">
                        <ResumeTab assessment={assessment} />
                    </TabsContent>

                    <TabsContent value="answers" className="flex flex-col gap-6">
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
                                {assessment.needs_manual_review ? (
                                    <Badge variant="outline">
                                        Needs review
                                    </Badge>
                                ) : null}
                            </div>
                            <CardDescription className="truncate">
                                {assessment.candidate.email ?? '-'}
                            </CardDescription>
                            <p className="truncate text-sm text-muted-foreground">
                                {assessment.campaign.role_title ?? 'Role'} ·{' '}
                                {assessment.campaign.title ?? 'Campaign'}
                            </p>
                        </div>
                    </div>

                    <AssessmentActionsMenu
                        assessment={assessment}
                        subject={subject}
                        body={body}
                    />
                </div>
            </CardHeader>
            <CardContent>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <MetaItem
                        label="Submitted"
                        value={formatDate(assessment.created_at)}
                    />
                    <MetaItem
                        label="Evaluated"
                        value={formatDate(assessment.evaluated_at)}
                    />
                    <MetaItem
                        label="Approved"
                        value={formatDate(assessment.approved_at)}
                    />
                    <MetaItem
                        label="Email sent"
                        value={formatDate(assessment.email_sent_at)}
                    />
                </div>
            </CardContent>
        </Card>
    );
}

function OverviewTab({ assessment }: { assessment: Assessment }) {
    return (
        <>
            {assessment.ai_justification ? (
                <Card>
                    <CardHeader>
                        <CardTitle>AI Justification</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="whitespace-pre-wrap text-sm text-muted-foreground">
                            {assessment.ai_justification}
                        </p>
                    </CardContent>
                </Card>
            ) : null}

            {assessment.critic_payload ? (
                <Card>
                    <CardHeader>
                        <CardTitle>Critic Review</CardTitle>
                        <CardDescription>
                            {assessment.critic_payload.summary ??
                                'No critic summary available.'}
                        </CardDescription>
                        <CardAction>
                            <div className="flex flex-col items-end gap-1.5">
                                <Badge variant="outline">
                                    {assessment.critic_payload.outcome ?? '-'}
                                </Badge>
                                {assessment.critic_payload
                                    .manual_review_required ? (
                                    <Badge variant="secondary">
                                        Manual review required
                                    </Badge>
                                ) : null}
                            </div>
                        </CardAction>
                    </CardHeader>
                    {assessment.critic_payload.findings &&
                    assessment.critic_payload.findings.length > 0 ? (
                        <CardContent>
                            <div className="flex flex-wrap gap-2">
                                {assessment.critic_payload.findings.map(
                                    (finding) => (
                                        <Badge key={finding} variant="outline">
                                            {finding}
                                        </Badge>
                                    ),
                                )}
                            </div>
                        </CardContent>
                    ) : null}
                </Card>
            ) : null}

            {assessment.section_scores.length > 0 ? (
                <Card>
                    <CardHeader>
                        <CardTitle>Section scores</CardTitle>
                        <CardDescription>
                            Per-section results used in the ranking package.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Section</TableHead>
                                    <TableHead>Weight</TableHead>
                                    <TableHead>Points</TableHead>
                                    <TableHead>Score</TableHead>
                                    <TableHead className="w-40">
                                        Progress
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {assessment.section_scores.map((section) => (
                                    <TableRow
                                        key={`${section.section_id ?? 'section'}-${section.title}`}
                                    >
                                        <TableCell className="font-medium">
                                            {section.title}
                                        </TableCell>
                                        <TableCell>{section.weight}</TableCell>
                                        <TableCell>
                                            {section.earned_points}/
                                            {section.total_points}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {section.score ?? '-'}%
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {section.score !== null ? (
                                                <Progress
                                                    value={section.score}
                                                />
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    -
                                                </span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            ) : null}
        </>
    );
}

function ResumeTab({ assessment }: { assessment: Assessment }) {
    const summary =
        assessment.resume_payload?.summary ??
        assessment.resume_justification ??
        null;

    return (
        <div className="grid gap-6 lg:grid-cols-[minmax(240px,280px)_minmax(0,1fr)]">
            <Card>
                <CardHeader>
                    <CardTitle>Resume file</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    <FieldGroup className="gap-4">
                        <MetaItem
                            label="Filename"
                            value={assessment.resume_original_name ?? '-'}
                        />
                        <MetaItem
                            label="Score"
                            value={scoreOrDash(assessment.resume_score)}
                        />
                        <MetaItem
                            label="Confidence"
                            value={
                                assessment.resume_payload?.confidence ?? '-'
                            }
                        />
                    </FieldGroup>
                    {assessment.resume_score !== null ? (
                        <Progress value={assessment.resume_score} />
                    ) : null}
                </CardContent>
            </Card>

            <div className="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Resume Screening</CardTitle>
                        <CardDescription>
                            AI screening summary and skill signals.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {summary ? (
                            <p className="whitespace-pre-wrap text-sm text-muted-foreground">
                                {summary}
                            </p>
                        ) : (
                            <Empty className="border border-dashed">
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <FileText />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        No resume screening yet
                                    </EmptyTitle>
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
                        items={
                            assessment.resume_payload?.matched_skills ?? []
                        }
                        variant="default"
                    />
                    <SkillGroup
                        title="Missing skills"
                        items={
                            assessment.resume_payload?.missing_skills ?? []
                        }
                        variant="outline"
                    />
                    <SkillGroup
                        title="Risk flags"
                        items={assessment.resume_payload?.risk_flags ?? []}
                        variant="destructive"
                    />
                    <SkillGroup
                        title="Interview probes"
                        items={
                            assessment.resume_payload?.interview_probes ?? []
                        }
                        variant="secondary"
                    />
                </div>
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

    return (
        <ScrollArea className="h-[70vh] rounded-lg">
            <div className="flex flex-col gap-4 pr-4">
                {assessment.answers_payload.map((answer, index) => (
                    <Card key={answer.question_id}>
                        <CardHeader>
                            <CardDescription>
                                Question {index + 1}
                            </CardDescription>
                            <CardTitle>{answer.question}</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            <Field>
                                <FieldLabel>Rubric</FieldLabel>
                                <FieldContent>
                                    <p className="whitespace-pre-wrap text-sm">
                                        {answer.rubric}
                                    </p>
                                </FieldContent>
                            </Field>
                            <Separator />
                            <Field>
                                <FieldLabel>Answer</FieldLabel>
                                <FieldContent>
                                    <p className="whitespace-pre-wrap text-sm">
                                        {answer.answer}
                                    </p>
                                </FieldContent>
                            </Field>
                        </CardContent>
                    </Card>
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
                                        data-invalid={
                                            hasBodyError || undefined
                                        }
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

                                <DialogFooter className="sm:justify-between">
                                    <RejectAssessmentDialog
                                        assessment={assessment}
                                    />
                                    <div className="flex flex-col-reverse gap-2 sm:flex-row">
                                        <DialogClose asChild>
                                            <Button
                                                type="button"
                                                variant="outline"
                                            >
                                                Cancel
                                            </Button>
                                        </DialogClose>
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
                                                <CheckCircle2 data-icon="inline-start" />
                                            )}
                                            Approve
                                        </Button>
                                    </div>
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
}: {
    label: string;
    score: number | null | undefined;
}) {
    return (
        <Card size="sm">
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle>
                    <span className="text-2xl tabular-nums">
                        {scoreOrDash(score)}
                    </span>
                </CardTitle>
            </CardHeader>
            {typeof score === 'number' ? (
                <CardContent>
                    <Progress value={score} />
                </CardContent>
            ) : null}
        </Card>
    );
}

function MetaItem({
    label,
    value,
}: {
    label: string;
    value: string | number;
}) {
    return (
        <Field>
            <FieldLabel>{label}</FieldLabel>
            <FieldContent>
                <p className="text-sm font-medium break-words">{value}</p>
            </FieldContent>
        </Field>
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

function AssessmentActionsMenu({
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
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline">
                        <EllipsisVertical data-icon="inline-start" />
                        Actions
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="min-w-48">
                    <DropdownMenuGroup>
                        <DropdownMenuItem
                            onSelect={() => setOpenAction('email')}
                        >
                            <Mail />
                            Interview Email
                        </DropdownMenuItem>
                    </DropdownMenuGroup>

                    {hasRecoveryAction ? (
                        <>
                            <DropdownMenuSeparator />
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
                        </>
                    ) : null}
                </DropdownMenuContent>
            </DropdownMenu>

            <InterviewEmailDialog
                assessment={assessment}
                subject={subject}
                body={body}
                open={openAction === 'email'}
                onOpenChange={(open) =>
                    setOpenAction(open ? 'email' : null)
                }
            />
            <RetryEvaluationDialog
                assessment={assessment}
                open={openAction === 'retry'}
                onOpenChange={(open) =>
                    setOpenAction(open ? 'retry' : null)
                }
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
                onOpenChange={(open) =>
                    setOpenAction(open ? 'promote' : null)
                }
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

function formErrorsFrom(
    errors: unknown,
): Record<string, string | undefined> {
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
                        Move this false negative into pending approval. If no
                        AI email draft exists, provide a manual draft here.
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
                                        data-invalid={
                                            hasBodyError || undefined
                                        }
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
                        const hasScoreError = Boolean(
                            formErrors.ranking_score,
                        );
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
                    <XCircle data-icon="inline-start" />
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
