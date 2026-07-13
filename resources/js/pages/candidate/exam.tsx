import { Form, Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, FileText, Shield } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import ExamSessionController from '@/actions/App/Http/Controllers/Candidate/ExamSessionController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useExamNavigationGuard } from '@/hooks/use-exam-navigation-guard';
import { useExamProctoring } from '@/hooks/use-exam-proctoring';
import {
    formatExamTimer,
    useExamTimer,
    useSectionExpiryReload,
} from '@/hooks/use-exam-timer';
import candidate from '@/routes/candidate';

type Question = {
    id: number;
    section_id: number;
    content: string;
    type: string;
    type_label: string;
    options: string[];
    matching_pairs: {
        prompts: string[];
        choices: string[];
    } | null;
    points: number;
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
    threshold_score: number;
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

type ReadyToStartProps = {
    state: 'ready_to_start';
    campaign: Campaign;
    sections: SectionSummary[];
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
    | ReadyToStartProps
    | ActiveSectionProps
    | ReadyToFinalizeProps
) & {
    errors?: {
        session?: string;
    };
};

export default function CandidateExam(props: Props) {
    return (
        <>
            <Head title="Candidate Exam" />

            <div className="mx-auto max-w-4xl space-y-6 p-4 md:p-8">
                {props.campaign ? (
                    <p className="text-sm text-muted-foreground">
                        {props.campaign.team.name} / {props.campaign.title} -{' '}
                        {props.campaign.role_title}
                        {props.campaign.seniority
                            ? `, ${props.campaign.seniority}`
                            : ''}
                    </p>
                ) : null}

                <InputError message={props.errors?.session} />

                {renderExamContent(props)}
            </div>
        </>
    );
}

function renderExamContent(props: Props) {
    switch (props.state) {
        case 'submitted':
            return <SubmittedState assessment={props.assessment} />;
        case 'no_campaign':
            return <EmptyQuestionsState />;
        case 'campaign_picker':
            return <CampaignPickerState campaigns={props.campaigns} />;
        case 'ready_to_start':
            return (
                <StartExamState
                    campaign={props.campaign}
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

function SubmittedState({ assessment }: { assessment: ExistingAssessment }) {
    return (
        <div className="rounded-lg border border-sidebar-border/70 p-6 dark:border-sidebar-border">
            <div className="flex items-start gap-4">
                <div className="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted">
                    <CheckCircle2 className="size-5 text-muted-foreground" />
                </div>
                <div className="space-y-3">
                    <div className="space-y-1">
                        <h2 className="text-base font-medium">
                            Assessment already submitted
                        </h2>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            You can only submit once for this campaign. Review
                            the current status from your assessment detail page.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={candidate.assessments.show(assessment.id)}>
                            View status
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    );
}

function EmptyQuestionsState() {
    return (
        <div className="rounded-lg border border-sidebar-border/70 p-6 dark:border-sidebar-border">
            <div className="flex items-start gap-4">
                <div className="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted">
                    <FileText className="size-5 text-muted-foreground" />
                </div>
                <div className="space-y-1">
                    <h2 className="text-base font-medium">
                        No active questions
                    </h2>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Open the invite link sent to your email to access an
                        assigned campaign exam.
                    </p>
                </div>
            </div>
        </div>
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
        <div className="space-y-4">
            <div className="space-y-1">
                <h2 className="text-base font-medium">Choose an exam</h2>
                <p className="max-w-2xl text-sm text-muted-foreground">
                    You have more than one assigned campaign. Select an exam to
                    continue.
                </p>
            </div>
            <ul className="divide-y divide-sidebar-border/70 rounded-lg border border-sidebar-border/70 dark:divide-sidebar-border dark:border-sidebar-border">
                {campaigns.map((campaign) => {
                    const progress = progressLabels[campaign.progress];

                    return (
                        <li key={campaign.id}>
                            <Link
                                href={candidate.campaigns.exam(campaign.id)}
                                className="flex items-center justify-between gap-4 px-4 py-3 transition-colors hover:bg-muted/50"
                            >
                                <div className="min-w-0 space-y-0.5">
                                    <p className="truncate text-sm font-medium">
                                        {campaign.title}
                                    </p>
                                    <p className="truncate text-sm text-muted-foreground">
                                        {campaign.team.name} ·{' '}
                                        {campaign.role_title}
                                    </p>
                                </div>
                                <Badge variant={progress.variant}>
                                    {progress.label}
                                </Badge>
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

function StartExamState({
    campaign,
    sectionCount,
    secureExam,
}: {
    campaign: Campaign;
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
            void document.exitFullscreen?.();
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
        <div className="space-y-6 rounded-lg border border-sidebar-border/70 p-6 dark:border-sidebar-border">
            <div className="flex items-start gap-3">
                <div className="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted">
                    <Shield className="size-5 text-muted-foreground" />
                </div>
                <div className="space-y-2">
                    <h2 className="text-base font-medium">Secure exam mode</h2>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        This assessment runs one section at a time with server
                        timers, fullscreen enforcement, and integrity warnings.
                        You cannot navigate back once the attempt starts.
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {sectionCount} section{sectionCount === 1 ? '' : 's'} to
                        complete.
                    </p>
                </div>
            </div>
            <div className="space-y-2">
                <Button
                    type="button"
                    disabled={processing}
                    onClick={() => {
                        void startExam();
                    }}
                >
                    {processing && <Spinner />}
                    Start secure exam
                </Button>
                <InputError message={setupError ?? undefined} />
            </div>
        </div>
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
        const updateSecureAccess = (): void => {
            setHasSecureAccess(
                !requiresFullscreen || document.fullscreenElement !== null,
            );
        };

        updateSecureAccess();
        document.addEventListener('fullscreenchange', updateSecureAccess);

        return () => {
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
                <div className="space-y-4 rounded-lg border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <div className="flex items-start gap-3">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted">
                            <Shield className="size-5 text-muted-foreground" />
                        </div>
                        <div className="space-y-2">
                            <h2 className="text-base font-medium">
                                Fullscreen required
                            </h2>
                            <p className="max-w-2xl text-sm text-muted-foreground">
                                Return to fullscreen to continue. Exam controls
                                remain locked until fullscreen is active.
                            </p>
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Button
                            type="button"
                            disabled={requestingFullscreen}
                            onClick={() => {
                                void acquireSecureAccess();
                            }}
                        >
                            {requestingFullscreen && <Spinner />}
                            Enter fullscreen
                        </Button>
                        <InputError message={setupError ?? undefined} />
                    </div>
                </div>
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

    function updateAnswer(questionId: number, value: string) {
        setAnswers((current) => ({
            ...current,
            [questionId]: value,
        }));
    }

    function saveAndAdvance() {
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
                        { preserveScroll: true },
                    );
                },
            },
        );
    }

    return (
        <div className="space-y-6">
            <IntegrityBanner examSession={examSession} />

            <section className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <p className="text-xs font-medium text-muted-foreground">
                            Section {sectionIndex} of {sections.length}
                        </p>
                        <h2 className="text-lg font-medium">
                            {currentSection.title}
                        </h2>
                        {currentSection.description ? (
                            <p className="text-sm text-muted-foreground">
                                {currentSection.description}
                            </p>
                        ) : null}
                    </div>
                    <div className="rounded-lg bg-muted px-3 py-2 text-sm font-medium">
                        {formatExamTimer(remainingSeconds, isPending)}
                    </div>
                </div>
            </section>

            <div className="space-y-4">
                {questions.map((question, index) => (
                    <section
                        key={question.id}
                        className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                    >
                        <div className="space-y-4">
                            <div className="space-y-1">
                                <p className="text-xs font-medium text-muted-foreground">
                                    Question {index + 1}
                                </p>
                                <h3 className="text-base font-medium">
                                    {question.content}
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    {question.type_label} - {question.points}{' '}
                                    pts
                                </p>
                            </div>
                            <AnswerField
                                question={question}
                                value={answers[question.id] ?? ''}
                                onAnswerChanged={updateAnswer}
                            />
                        </div>
                    </section>
                ))}
            </div>

            <Button
                type="button"
                onClick={saveAndAdvance}
                data-test="exam-next-section-button"
            >
                {sectionIndex === sections.length
                    ? 'Complete sections'
                    : 'Save and continue'}
            </Button>
        </div>
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

    const [resumeName, setResumeName] = useState('');

    return (
        <div className="space-y-6">
            <IntegrityBanner examSession={examSession} />

            <section className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                <div className="space-y-2">
                    <h2 className="text-base font-medium">
                        Upload resume and submit
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        All sections are complete. Upload your PDF resume to
                        finalize the assessment.
                    </p>
                </div>
            </section>

            <Form
                {...ExamSessionController.finalize.form([
                    campaign.id,
                    examSession.id,
                ])}
                className="space-y-4"
            >
                {({ errors, processing, progress }) => (
                    <>
                        <div className="grid gap-2">
                            <label
                                htmlFor="resume"
                                className="text-sm font-medium"
                            >
                                Resume PDF
                            </label>
                            <input
                                id="resume"
                                name="resume"
                                type="file"
                                accept="application/pdf,.pdf"
                                required
                                onChange={(event) =>
                                    setResumeName(
                                        event.currentTarget.files?.[0]?.name ??
                                            '',
                                    )
                                }
                                className="flex min-h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm file:font-medium"
                            />
                            {resumeName ? (
                                <p className="text-xs text-muted-foreground">
                                    Selected: {resumeName}
                                </p>
                            ) : null}
                            {progress ? (
                                <p className="text-xs text-muted-foreground">
                                    Uploading {progress.percentage}%
                                </p>
                            ) : null}
                            <InputError message={errors.resume} />
                        </div>
                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="submit-assessment-button"
                        >
                            {processing && <Spinner />}
                            Submit assessment
                        </Button>
                    </>
                )}
            </Form>
        </div>
    );
}

function IntegrityBanner({ examSession }: { examSession: ExamSessionProps }) {
    return (
        <div className="flex items-start gap-3 rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-sm">
            <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600" />
            <div className="space-y-1">
                <p className="font-medium">Secure exam in progress</p>
                <p className="text-muted-foreground">
                    Stay in fullscreen. Copy, paste, and back navigation are
                    restricted. Integrity warnings: {examSession.warning_count}{' '}
                    / {examSession.max_warnings}.
                </p>
            </div>
        </div>
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

    if (
        question.type === 'matching_pairs' &&
        question.matching_pairs !== null &&
        question.matching_pairs.prompts.length > 0
    ) {
        return (
            <MatchingPairsAnswerField
                question={question}
                value={value}
                onAnswerChanged={onAnswerChanged}
                error={error}
            />
        );
    }

    if (question.type === 'multiple_choice' || question.type === 'yes_no') {
        const options =
            question.type === 'yes_no' && question.options.length === 0
                ? ['Yes', 'No']
                : question.options;

        return (
            <fieldset className="grid gap-3">
                <legend className="text-sm font-medium">Answer</legend>
                <div className="grid gap-2">
                    {options.map((option) => (
                        <label
                            key={option}
                            className="flex items-center gap-3 rounded-md border border-border px-3 py-2 text-sm"
                        >
                            <input
                                type="radio"
                                name={`answer-${question.id}`}
                                value={option}
                                checked={value === option}
                                required
                                onChange={(event) =>
                                    onAnswerChanged(
                                        question.id,
                                        event.currentTarget.value,
                                    )
                                }
                            />
                            <span>{option}</span>
                        </label>
                    ))}
                </div>
                <InputError message={error} />
            </fieldset>
        );
    }

    if (question.type === 'fill_blank') {
        return (
            <div className="grid gap-2">
                <label htmlFor={inputId} className="text-sm font-medium">
                    Answer
                </label>
                <input
                    id={inputId}
                    value={value}
                    required
                    onChange={(event) =>
                        onAnswerChanged(question.id, event.currentTarget.value)
                    }
                    className="flex min-h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    placeholder="Write the missing term."
                />
                <InputError message={error} />
            </div>
        );
    }

    return (
        <div className="grid gap-2">
            <label htmlFor={inputId} className="text-sm font-medium">
                Answer
            </label>
            <textarea
                id={inputId}
                rows={question.type === 'short_text' ? 4 : 8}
                value={value}
                required
                onChange={(event) =>
                    onAnswerChanged(question.id, event.currentTarget.value)
                }
                className="flex min-h-40 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                placeholder="Write your answer here."
            />
            <InputError message={error} />
        </div>
    );
}

function MatchingPairsAnswerField({
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
    const prompts = useMemo(
        () => question.matching_pairs?.prompts ?? [],
        [question.matching_pairs],
    );
    const choices = question.matching_pairs?.choices ?? [];
    const selections = useMemo(
        () => parseMatchingSelections(value, prompts),
        [prompts, value],
    );

    function serializeSelection(
        nextSelections: Record<string, string>,
    ): string {
        return prompts
            .map((prompt) => {
                const choice = nextSelections[prompt];

                return choice ? `${prompt} = ${choice}` : '';
            })
            .filter((pair) => pair !== '')
            .join('\n');
    }

    function updateSelection(prompt: string, choice: string) {
        const nextSelections = {
            ...selections,
            [prompt]: choice,
        };

        onAnswerChanged(question.id, serializeSelection(nextSelections));
    }

    return (
        <fieldset className="grid gap-4">
            <legend className="text-sm font-medium">Answer</legend>
            <div className="grid gap-3">
                {prompts.map((prompt) => (
                    <div
                        key={prompt}
                        className="grid gap-2 rounded-md border border-border p-3 md:grid-cols-[minmax(0,1fr)_220px] md:items-center"
                    >
                        <p className="text-sm font-medium">{prompt}</p>
                        <select
                            required
                            value={selections[prompt] ?? ''}
                            onChange={(event) =>
                                updateSelection(
                                    prompt,
                                    event.currentTarget.value,
                                )
                            }
                            className="flex min-h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="">Choose a match</option>
                            {choices.map((choice) => (
                                <option
                                    key={`${prompt}-${choice}`}
                                    value={choice}
                                >
                                    {choice}
                                </option>
                            ))}
                        </select>
                    </div>
                ))}
            </div>
            <InputError message={error} />
        </fieldset>
    );
}

function parseMatchingSelections(
    value: string,
    prompts: string[],
): Record<string, string> {
    const selections: Record<string, string> = {};

    for (const line of value.split('\n')) {
        const match = line.match(/^(.+?)\s*=\s*(.+)$/u);

        if (match) {
            selections[match[1].trim()] = match[2].trim();
        }
    }

    for (const prompt of prompts) {
        if (!(prompt in selections)) {
            selections[prompt] = '';
        }
    }

    return selections;
}

CandidateExam.layout = {
    breadcrumbs: [
        {
            title: 'Candidate Exam',
            href: candidate.exam(),
        },
    ],
};
