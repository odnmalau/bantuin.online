import { Form, Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    Clock3,
    FileCheck2,
    FileText,
    LockKeyhole,
    Shield,
} from 'lucide-react';
import { Fragment, useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { store as storeCandidateResume } from '@/actions/App/Http/Controllers/Candidate/CandidateApplicationController';
import ExamSessionController from '@/actions/App/Http/Controllers/Candidate/ExamSessionController';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import {
    Item,
    ItemActions,
    ItemContent,
    ItemDescription,
    ItemGroup,
    ItemMedia,
    ItemSeparator,
    ItemTitle,
} from '@/components/ui/item';
import { Progress } from '@/components/ui/progress';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useExamNavigationGuard } from '@/hooks/use-exam-navigation-guard';
import { useExamProctoring } from '@/hooks/use-exam-proctoring';
import {
    formatExamTimer,
    useExamTimer,
    useSectionExpiryReload,
} from '@/hooks/use-exam-timer';
import { exitSecureExamFullscreen } from '@/lib/secure-exam-fullscreen';
import candidate from '@/routes/candidate';

type Question = {
    id: number;
    section_id: number;
    content: string;
    type: string;
    type_label: string;
    max_characters: number;
    section_title: string | null;
    sort_order: number;
};

type SectionSummary = {
    id: number;
    title: string;
    description: string | null;
    duration_minutes: number | null;
    sort_order: number;
    question_count: number;
};

type CurrentSection = SectionSummary & {
    questions: Question[];
};

type ExamSessionProps = {
    id: number;
    status: string;
    current_section_id: number | null;
    current_section_started_at: string | null;
    current_section_expires_at: string | null;
    completed_section_ids: number[];
    warning_count: number;
    max_warnings: number;
    answer_drafts: Record<string, string>;
    ready_to_finalize: boolean;
    secure_exam: {
        require_fullscreen: boolean;
        block_copy_paste: boolean;
    };
};

type Campaign = {
    id: number;
    title: string;
    role_title: string;
    team: {
        name: string;
    };
    seniority: string | null;
};

type ExistingAssessment = {
    id: number;
    status: string;
    created_at: string;
};

type NoCampaignProps = {
    state: 'no_campaign';
    campaign: null;
};

type PickerCampaign = {
    id: number;
    title: string;
    role_title: string;
    team: {
        name: string;
    };
    progress: 'not_started' | 'in_progress' | 'submitted';
};

type CampaignPickerProps = {
    state: 'campaign_picker';
    campaign: null;
    campaigns: PickerCampaign[];
};

type SubmittedProps = {
    state: 'submitted';
    campaign: Campaign;
    assessment: ExistingAssessment;
};

type CandidateApplication = {
    resume_original_name: string;
    resume_uploaded_at: string;
    locked: boolean;
};

type ResumeRequiredProps = {
    state: 'resume_required';
    campaign: Campaign;
    sections: SectionSummary[];
};

type ReadyToStartProps = {
    state: 'ready_to_start';
    campaign: Campaign;
    sections: SectionSummary[];
    application: CandidateApplication;
    secure_exam: ExamSessionProps['secure_exam'];
};

type ActiveSectionProps = {
    state: 'active_section';
    campaign: Campaign;
    sections: SectionSummary[];
    currentSection: CurrentSection;
    questions: Question[];
    examSession: ExamSessionProps;
};

type ReadyToFinalizeProps = {
    state: 'ready_to_finalize';
    campaign: Campaign;
    sections: SectionSummary[];
    examSession: ExamSessionProps;
};

type Props = (
    | NoCampaignProps
    | CampaignPickerProps
    | SubmittedProps
    | ResumeRequiredProps
    | ReadyToStartProps
    | ActiveSectionProps
    | ReadyToFinalizeProps
) & {
    errors?: {
        session?: string;
    };
};

export default function CandidateExam(props: Props) {
    useEffect(() => {
        if (
            props.state === 'submitted' &&
            document.fullscreenElement !== null
        ) {
            exitSecureExamFullscreen();
        }
    }, [props.state]);

    return (
        <>
            <Head title="Candidate Exam" />

            <div className="flex w-full flex-col gap-6 p-4">
                {props.errors?.session ? (
                    <Alert variant="destructive">
                        <AlertTriangle />
                        <AlertTitle>Unable to continue</AlertTitle>
                        <AlertDescription>
                            {props.errors.session}
                        </AlertDescription>
                    </Alert>
                ) : null}

                {renderExamContent(props)}
            </div>
        </>
    );
}

function renderExamContent(props: Props) {
    switch (props.state) {
        case 'submitted':
            return (
                <SubmittedState
                    campaign={props.campaign}
                    assessment={props.assessment}
                />
            );
        case 'no_campaign':
            return <EmptyQuestionsState />;
        case 'campaign_picker':
            return <CampaignPickerState campaigns={props.campaigns} />;
        case 'resume_required':
            return (
                <ResumeRequiredState
                    campaign={props.campaign}
                    sectionCount={props.sections.length}
                />
            );
        case 'ready_to_start':
            return (
                <StartExamState
                    campaign={props.campaign}
                    application={props.application}
                    sectionCount={props.sections.length}
                    secureExam={props.secure_exam}
                />
            );
        case 'ready_to_finalize':
            return (
                <SecureExamAccess
                    campaignId={props.campaign.id}
                    examSession={props.examSession}
                >
                    <FinalizeExamState
                        campaign={props.campaign}
                        examSession={props.examSession}
                    />
                </SecureExamAccess>
            );
        case 'active_section':
            return (
                <SecureExamAccess
                    campaignId={props.campaign.id}
                    examSession={props.examSession}
                >
                    <ActiveSectionExam
                        campaign={props.campaign}
                        sections={props.sections}
                        currentSection={props.currentSection}
                        questions={props.questions}
                        examSession={props.examSession}
                    />
                </SecureExamAccess>
            );
    }
}

function SubmittedState({
    campaign,
    assessment,
}: {
    campaign: Campaign;
    assessment: ExistingAssessment;
}) {
    return (
        <Card className="gap-0 overflow-hidden">
            <CampaignCardHeader campaign={campaign} badge="Submitted" />
            <CardContent className="bg-background-200 py-(--card-spacing)">
                <Empty className="border bg-background">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <CheckCircle2 />
                        </EmptyMedia>
                        <EmptyTitle>Assessment already submitted</EmptyTitle>
                        <EmptyDescription>
                            This campaign can only be submitted once. Your
                            assessment status and submission details remain
                            available.
                        </EmptyDescription>
                    </EmptyHeader>
                    <EmptyContent>
                        <Button asChild>
                            <Link
                                href={candidate.assessments.show(assessment.id)}
                            >
                                View assessment
                                <ArrowRight data-icon="inline-end" />
                            </Link>
                        </Button>
                    </EmptyContent>
                </Empty>
            </CardContent>
        </Card>
    );
}

function EmptyQuestionsState() {
    return (
        <Card>
            <CardContent>
                <Empty>
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <FileText />
                        </EmptyMedia>
                        <EmptyTitle>No assigned assessments</EmptyTitle>
                        <EmptyDescription>
                            Open the invitation link sent to your email to
                            access a campaign assessment.
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            </CardContent>
        </Card>
    );
}

const progressLabels: Record<
    PickerCampaign['progress'],
    { label: string; variant: 'default' | 'secondary' | 'outline' }
> = {
    not_started: { label: 'Not started', variant: 'outline' },
    in_progress: { label: 'In progress', variant: 'default' },
    submitted: { label: 'Submitted', variant: 'secondary' },
};

function CampaignPickerState({ campaigns }: { campaigns: PickerCampaign[] }) {
    return (
        <Card className="gap-0 overflow-hidden">
            <CardHeader className="border-b">
                <CardTitle>Your assessments</CardTitle>
                <CardDescription>
                    Select a campaign to continue its application or assessment.
                </CardDescription>
                <CardAction>
                    <Badge variant="secondary">
                        {campaigns.length} assigned
                    </Badge>
                </CardAction>
            </CardHeader>
            <CardContent className="bg-background-200 py-(--card-spacing)">
                <ItemGroup className="gap-0 overflow-hidden rounded-md border bg-background">
                    {campaigns.map((campaign, index) => {
                        const progress = progressLabels[campaign.progress];

                        return (
                            <Fragment key={campaign.id}>
                                {index > 0 ? (
                                    <ItemSeparator className="my-0" />
                                ) : null}
                                <Item asChild className="rounded-none border-0">
                                    <Link
                                        href={candidate.campaigns.exam(
                                            campaign.id,
                                        )}
                                    >
                                        <ItemMedia variant="icon">
                                            <FileCheck2 />
                                        </ItemMedia>
                                        <ItemContent>
                                            <ItemTitle>
                                                {campaign.title}
                                            </ItemTitle>
                                            <ItemDescription>
                                                {campaign.team.name} ·{' '}
                                                {campaign.role_title}
                                            </ItemDescription>
                                        </ItemContent>
                                        <ItemActions>
                                            <Badge variant={progress.variant}>
                                                {progress.label}
                                            </Badge>
                                            <ArrowRight />
                                        </ItemActions>
                                    </Link>
                                </Item>
                            </Fragment>
                        );
                    })}
                </ItemGroup>
            </CardContent>
        </Card>
    );
}

function CampaignCardHeader({
    campaign,
    badge,
}: {
    campaign: Campaign;
    badge: string;
}) {
    return (
        <CardHeader className="border-b">
            <CardTitle>{campaign.title}</CardTitle>
            <CardDescription>
                {campaign.team.name} · {campaign.role_title}
                {campaign.seniority ? ` · ${campaign.seniority}` : ''}
            </CardDescription>
            <CardAction>
                <Badge variant="secondary">{badge}</Badge>
            </CardAction>
        </CardHeader>
    );
}

function StartExamState({
    campaign,
    application,
    sectionCount,
    secureExam,
}: {
    campaign: Campaign;
    application: CandidateApplication;
    sectionCount: number;
    secureExam: ExamSessionProps['secure_exam'];
}) {
    const [processing, setProcessing] = useState(false);
    const [setupError, setSetupError] = useState<string | null>(null);

    const startExam = async (): Promise<void> => {
        setProcessing(true);
        setSetupError(null);

        let enteredFullscreen = false;

        const leaveFullscreenAfterFailure = (): void => {
            if (!enteredFullscreen || document.fullscreenElement === null) {
                return;
            }

            enteredFullscreen = false;
            exitSecureExamFullscreen();
        };

        if (
            secureExam.require_fullscreen &&
            document.fullscreenElement === null
        ) {
            const requestFullscreen =
                document.documentElement.requestFullscreen?.bind(
                    document.documentElement,
                );

            if (!requestFullscreen) {
                setSetupError(
                    'Fullscreen is required to start this exam, but your browser does not support it.',
                );
                setProcessing(false);

                return;
            }

            try {
                await requestFullscreen();
                enteredFullscreen = true;
            } catch {
                setSetupError(
                    'Fullscreen is required to start this exam. Allow fullscreen and try again.',
                );
                setProcessing(false);

                return;
            }
        }

        router.post(
            ExamSessionController.store.url(campaign.id),
            {},
            {
                onFinish: () => {
                    setProcessing(false);
                },
                onError: leaveFullscreenAfterFailure,
                onNetworkError: leaveFullscreenAfterFailure,
                onHttpException: leaveFullscreenAfterFailure,
                onCancel: leaveFullscreenAfterFailure,
            },
        );
    };

    return (
        <Card className="gap-0 overflow-hidden">
            <CampaignCardHeader campaign={campaign} badge="Ready" />
            <CardContent className="grid gap-4 bg-background-200 py-(--card-spacing) lg:grid-cols-2">
                <Alert>
                    <Shield />
                    <AlertTitle>Secure assessment</AlertTitle>
                    <AlertDescription>
                        {sectionCount} section
                        {sectionCount === 1 ? '' : 's'} with server timers,
                        fullscreen enforcement, and integrity monitoring. You
                        cannot return to a completed section.
                    </AlertDescription>
                </Alert>
                <ResumeUploadForm
                    campaign={campaign}
                    application={application}
                />
            </CardContent>
            <CardFooter className="flex-col items-stretch gap-2 border-t bg-background sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm text-muted-foreground">
                    Starting the assessment locks your saved resume.
                </p>
                <Button
                    type="button"
                    disabled={processing}
                    onClick={() => {
                        void startExam();
                    }}
                >
                    {processing ? (
                        <Spinner data-icon="inline-start" />
                    ) : (
                        <LockKeyhole data-icon="inline-start" />
                    )}
                    Start secure exam
                </Button>
                <InputError message={setupError ?? undefined} />
            </CardFooter>
        </Card>
    );
}

function ResumeRequiredState({
    campaign,
    sectionCount,
}: {
    campaign: Campaign;
    sectionCount: number;
}) {
    return (
        <Card className="gap-0 overflow-hidden">
            <CampaignCardHeader campaign={campaign} badge="Application" />
            <CardContent className="bg-background-200 py-(--card-spacing)">
                <ResumeUploadForm
                    campaign={campaign}
                    description={`Upload a PDF resume before starting this ${sectionCount}-section assessment.`}
                />
            </CardContent>
        </Card>
    );
}

function ResumeUploadForm({
    campaign,
    application,
    description,
}: {
    campaign: Campaign;
    application?: CandidateApplication;
    description?: string;
}) {
    const [resumeName, setResumeName] = useState('');

    return (
        <Card size="sm" className="gap-0">
            <CardHeader className="border-b">
                <CardTitle>
                    {application ? 'Saved resume' : 'Add your resume'}
                </CardTitle>
                <CardDescription>
                    {application?.resume_original_name ??
                        description ??
                        'PDF format, up to the configured upload limit.'}
                </CardDescription>
                {application ? (
                    <CardAction>
                        <Badge variant="outline">Saved</Badge>
                    </CardAction>
                ) : null}
            </CardHeader>
            <Form {...storeCandidateResume.form(campaign.id)}>
                {({ errors, processing, progress }) => (
                    <>
                        <CardContent className="py-(--card-spacing)">
                            <FieldGroup className="gap-4">
                                <Field data-invalid={Boolean(errors.resume)}>
                                    <FieldLabel htmlFor="resume">
                                        {application
                                            ? 'Choose a replacement PDF'
                                            : 'Resume PDF'}
                                    </FieldLabel>
                                    <input
                                        id="resume"
                                        name="resume"
                                        type="file"
                                        accept="application/pdf,.pdf"
                                        required
                                        aria-invalid={Boolean(errors.resume)}
                                        onChange={(event) =>
                                            setResumeName(
                                                event.currentTarget.files?.[0]
                                                    ?.name ?? '',
                                            )
                                        }
                                        className="flex min-h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm file:font-medium"
                                    />
                                    <FieldDescription>
                                        {resumeName
                                            ? `Selected: ${resumeName}`
                                            : 'Your resume remains editable until the assessment starts.'}
                                    </FieldDescription>
                                    {progress ? (
                                        <Progress
                                            value={progress.percentage}
                                            aria-label={`Uploading ${progress.percentage}%`}
                                        />
                                    ) : null}
                                    <FieldError>{errors.resume}</FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                        <CardFooter className="justify-end border-t bg-background">
                            <Button type="submit" disabled={processing}>
                                {processing && (
                                    <Spinner data-icon="inline-start" />
                                )}
                                {application ? 'Replace resume' : 'Save resume'}
                            </Button>
                        </CardFooter>
                    </>
                )}
            </Form>
        </Card>
    );
}

function SecureExamAccess({
    campaignId,
    examSession,
    children,
}: {
    campaignId: number;
    examSession: ExamSessionProps;
    children: ReactNode;
}) {
    const requiresFullscreen = examSession.secure_exam.require_fullscreen;
    const [hasSecureAccess, setHasSecureAccess] = useState(
        () =>
            !requiresFullscreen ||
            (typeof document !== 'undefined' &&
                document.fullscreenElement !== null),
    );
    const [setupError, setSetupError] = useState<string | null>(null);
    const [requestingFullscreen, setRequestingFullscreen] = useState(false);

    useEffect(() => {
        document.documentElement.dataset.secureExamActive = 'true';

        const updateSecureAccess = (): void => {
            setHasSecureAccess(
                !requiresFullscreen || document.fullscreenElement !== null,
            );
        };

        updateSecureAccess();
        document.addEventListener('fullscreenchange', updateSecureAccess);

        return () => {
            delete document.documentElement.dataset.secureExamActive;
            document.removeEventListener(
                'fullscreenchange',
                updateSecureAccess,
            );
        };
    }, [requiresFullscreen]);

    useExamProctoring({
        campaignId,
        sessionId: examSession.id,
        enabled: hasSecureAccess,
        secureExam: examSession.secure_exam,
    });

    const acquireSecureAccess = async (): Promise<void> => {
        setRequestingFullscreen(true);
        setSetupError(null);

        const requestFullscreen =
            document.documentElement.requestFullscreen?.bind(
                document.documentElement,
            );

        if (!requestFullscreen) {
            setSetupError(
                'Fullscreen is required to continue this exam, but your browser does not support it.',
            );
            setRequestingFullscreen(false);

            return;
        }

        try {
            await requestFullscreen();
            setHasSecureAccess(document.fullscreenElement !== null);
        } catch {
            setSetupError(
                'Fullscreen is required to continue this exam. Allow fullscreen and try again.',
            );
        } finally {
            setRequestingFullscreen(false);
        }
    };

    return (
        <>
            {!hasSecureAccess ? (
                <Card className="gap-0 overflow-hidden bg-background-200">
                    <CardHeader>
                        <CardTitle>Fullscreen required</CardTitle>
                        <CardDescription>
                            Return to fullscreen to unlock your assessment
                            controls and continue the active section.
                        </CardDescription>
                        <CardAction>
                            <Badge variant="outline">Paused</Badge>
                        </CardAction>
                    </CardHeader>
                    <CardFooter className="flex-col items-stretch gap-2 border-t sm:flex-row sm:items-center">
                        <Button
                            type="button"
                            disabled={requestingFullscreen}
                            onClick={() => {
                                void acquireSecureAccess();
                            }}
                        >
                            {requestingFullscreen ? (
                                <Spinner data-icon="inline-start" />
                            ) : (
                                <Shield data-icon="inline-start" />
                            )}
                            Enter fullscreen
                        </Button>
                        <InputError message={setupError ?? undefined} />
                    </CardFooter>
                </Card>
            ) : null}

            <div hidden={!hasSecureAccess} inert={!hasSecureAccess}>
                {children}
            </div>
        </>
    );
}

function ActiveSectionExam({
    campaign,
    sections,
    currentSection,
    questions,
    examSession,
}: {
    campaign: Campaign;
    sections: SectionSummary[];
    currentSection: CurrentSection;
    questions: Question[];
    examSession: ExamSessionProps;
}) {
    const initialAnswers = useMemo(() => {
        const drafts: Record<number, string> = {};

        for (const question of questions) {
            const draft = examSession.answer_drafts[String(question.id)];

            if (typeof draft === 'string') {
                drafts[question.id] = draft;
            }
        }

        return drafts;
    }, [examSession.answer_drafts, questions]);

    const answerSeed = useMemo(
        () => `${currentSection.id}:${JSON.stringify(initialAnswers)}`,
        [currentSection.id, initialAnswers],
    );
    const [storedAnswerSeed, setStoredAnswerSeed] = useState(answerSeed);
    const [answers, setAnswers] =
        useState<Record<number, string>>(initialAnswers);
    const [isAdvancing, setIsAdvancing] = useState(false);

    if (storedAnswerSeed !== answerSeed) {
        setStoredAnswerSeed(answerSeed);
        setAnswers(initialAnswers);
    }

    const { remainingSeconds, isExpired, isPending } = useExamTimer(
        examSession.current_section_expires_at,
    );

    useSectionExpiryReload(isExpired);
    useExamNavigationGuard(true);

    const sectionIndex =
        sections.findIndex((section) => section.id === currentSection.id) + 1;
    const isSectionComplete = questions.every(
        (question) => (answers[question.id] ?? '').trim().length > 0,
    );

    function updateAnswer(questionId: number, value: string) {
        setAnswers((current) => ({
            ...current,
            [questionId]: value,
        }));
    }

    function saveAndAdvance() {
        if (!isSectionComplete || isAdvancing || isExpired) {
            return;
        }

        setIsAdvancing(true);

        const stopAdvancing = (): void => {
            setIsAdvancing(false);
        };

        router.patch(
            ExamSessionController.update.url([campaign.id, examSession.id]),
            { answers: stringifyAnswerKeys(answers) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    router.post(
                        ExamSessionController.advance.url([
                            campaign.id,
                            examSession.id,
                        ]),
                        {},
                        {
                            preserveScroll: true,
                            onFinish: stopAdvancing,
                        },
                    );
                },
                onError: stopAdvancing,
                onCancel: stopAdvancing,
                onNetworkError: stopAdvancing,
                onHttpException: stopAdvancing,
            },
        );
    }

    return (
        <Card className="gap-0 overflow-hidden">
            <CardHeader className="border-b">
                <CardTitle>{currentSection.title}</CardTitle>
                <CardDescription>
                    {campaign.team.name} · {campaign.title}
                    {currentSection.description
                        ? ` · ${currentSection.description}`
                        : ''}
                </CardDescription>
                <CardAction>
                    <Badge variant="outline">
                        <Clock3 />
                        <span className="tabular-nums">
                            {formatExamTimer(remainingSeconds, isPending)}
                        </span>
                    </Badge>
                </CardAction>
                <div className="col-span-full flex flex-col gap-2 pt-3">
                    <div className="flex items-center justify-between gap-4 text-sm text-muted-foreground">
                        <span>
                            Section {sectionIndex} of {sections.length}
                        </span>
                        <span>
                            {questions.length} question
                            {questions.length === 1 ? '' : 's'}
                        </span>
                    </div>
                    <Progress
                        value={(sectionIndex / sections.length) * 100}
                        aria-label={`Section ${sectionIndex} of ${sections.length}`}
                    />
                </div>
            </CardHeader>

            <CardContent className="flex flex-col gap-4 bg-background-200 py-(--card-spacing)">
                <IntegrityBanner examSession={examSession} />

                {questions.map((question, index) => (
                    <Card key={question.id} size="sm" className="gap-0">
                        <CardHeader className="border-b">
                            <CardTitle>{question.content}</CardTitle>
                            <CardDescription>
                                Question {index + 1} of {questions.length}
                            </CardDescription>
                            <CardAction>
                                <Badge variant="secondary">
                                    {question.type_label}
                                </Badge>
                            </CardAction>
                        </CardHeader>
                        <CardContent className="py-(--card-spacing)">
                            <AnswerField
                                question={question}
                                value={answers[question.id] ?? ''}
                                onAnswerChanged={updateAnswer}
                            />
                        </CardContent>
                    </Card>
                ))}
            </CardContent>

            <CardFooter className="flex-col items-stretch gap-3 border-t bg-background sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm text-muted-foreground">
                    Answer every question in this section before continuing.
                </p>
                <Button
                    type="button"
                    onClick={saveAndAdvance}
                    disabled={!isSectionComplete || isAdvancing || isExpired}
                    data-test="exam-next-section-button"
                >
                    {isAdvancing ? (
                        <>
                            <Spinner data-icon="inline-start" />
                            Saving section...
                        </>
                    ) : (
                        <>
                            {sectionIndex === sections.length
                                ? 'Complete sections'
                                : 'Save and continue'}
                            <ArrowRight data-icon="inline-end" />
                        </>
                    )}
                </Button>
            </CardFooter>
        </Card>
    );
}

function FinalizeExamState({
    campaign,
    examSession,
}: {
    campaign: Campaign;
    examSession: ExamSessionProps;
}) {
    useExamNavigationGuard(true);

    return (
        <Card className="gap-0 overflow-hidden">
            <CampaignCardHeader campaign={campaign} badge="Complete" />
            <CardContent className="flex flex-col gap-4 bg-background-200 py-(--card-spacing)">
                <IntegrityBanner examSession={examSession} />
                <Alert>
                    <CheckCircle2 />
                    <AlertTitle>All sections completed</AlertTitle>
                    <AlertDescription>
                        Your answers and saved resume are ready. Submit once to
                        send this assessment for evaluation.
                    </AlertDescription>
                </Alert>
            </CardContent>
            <CardFooter className="justify-end border-t bg-background">
                <Form
                    {...ExamSessionController.finalize.form([
                        campaign.id,
                        examSession.id,
                    ])}
                    onSuccess={exitSecureExamFullscreen}
                >
                    {({ processing }) => (
                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="submit-assessment-button"
                        >
                            {processing && <Spinner data-icon="inline-start" />}
                            Submit assessment
                        </Button>
                    )}
                </Form>
            </CardFooter>
        </Card>
    );
}

function IntegrityBanner({ examSession }: { examSession: ExamSessionProps }) {
    return (
        <Alert>
            <Shield />
            <AlertTitle>Secure exam in progress</AlertTitle>
            <AlertDescription>
                Stay in fullscreen. Copy, paste, and back navigation are
                restricted. Integrity warnings: {examSession.warning_count} /{' '}
                {examSession.max_warnings}.
            </AlertDescription>
        </Alert>
    );
}

function stringifyAnswerKeys(
    answers: Record<number, string>,
): Record<string, string> {
    return Object.fromEntries(
        Object.entries(answers).map(([key, value]) => [String(key), value]),
    );
}

function AnswerField({
    question,
    value,
    onAnswerChanged,
    error,
}: {
    question: Question;
    value: string;
    onAnswerChanged: (questionId: number, value: string) => void;
    error?: string;
}) {
    const inputId = `answer-${question.id}`;

    return (
        <Field data-invalid={Boolean(error)}>
            <FieldLabel htmlFor={inputId}>Your answer</FieldLabel>
            <Textarea
                id={inputId}
                rows={question.type === 'short_text' ? 4 : 8}
                value={value}
                required
                aria-invalid={Boolean(error)}
                maxLength={question.max_characters}
                onChange={(event) =>
                    onAnswerChanged(question.id, event.currentTarget.value)
                }
                className="min-h-40"
                placeholder="Write your answer here."
            />
            <FieldDescription className="text-right">
                {value.length.toLocaleString()} /{' '}
                {question.max_characters.toLocaleString()} characters
            </FieldDescription>
            <FieldError>{error}</FieldError>
        </Field>
    );
}

CandidateExam.layout = {
    breadcrumbs: [
        {
            title: 'Candidate Exam',
            href: candidate.exam(),
        },
    ],
};
