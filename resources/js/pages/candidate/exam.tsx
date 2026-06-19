import { Form, Head, Link } from '@inertiajs/react';
import { CheckCircle2, FileText, Upload } from 'lucide-react';
import { useState } from 'react';
import AssessmentController from '@/actions/App/Http/Controllers/Candidate/AssessmentController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import candidate from '@/routes/candidate';

type Question = {
    id: number;
    section_id: number;
    content: string;
    type: string;
    type_label: string;
    options: string[];
    points: number;
    section_title: string | null;
    sort_order: number;
};

type Section = {
    id: number;
    title: string;
    description: string | null;
    duration_minutes: number | null;
    sort_order: number;
    question_count: number;
    questions: Question[];
};

type Campaign = {
    id: number;
    title: string;
    role_title: string;
    seniority: string | null;
    threshold_score: number;
};

type ExistingAssessment = {
    id: number;
    status: string;
    created_at: string;
};

type Props = {
    campaign: Campaign | null;
    sections: Section[];
    questions: Question[];
    assessment: ExistingAssessment | null;
};

export default function CandidateExam({
    campaign,
    sections,
    questions,
    assessment,
}: Props) {
    const content = renderExamContent({
        campaign,
        sections,
        questions,
        assessment,
    });

    return (
        <>
            <Head title="Candidate Exam" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Candidate Exam"
                    description={
                        campaign
                            ? `${campaign.title} - ${campaign.role_title}${campaign.seniority ? `, ${campaign.seniority}` : ''}`
                            : 'No active campaign is available right now.'
                    }
                />

                {content}
            </div>
        </>
    );
}

function renderExamContent({
    campaign,
    sections,
    questions,
    assessment,
}: Props) {
    if (assessment !== null) {
        return <SubmittedState assessment={assessment} />;
    }

    if (campaign === null || questions.length === 0 || sections.length === 0) {
        return <EmptyQuestionsState />;
    }

    return <ExamForm campaign={campaign} sections={sections} />;
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
                        assigned campaign exam. If you were invited to multiple
                        campaigns, use the link for the campaign you want to
                        take.
                    </p>
                </div>
            </div>
        </div>
    );
}

function ExamForm({
    campaign,
    sections,
}: {
    campaign: Campaign;
    sections: Section[];
}) {
    const [resumeName, setResumeName] = useState('');
    const [answers, setAnswers] = useState<Record<number, string>>({});
    const totalQuestions = sections.reduce(
        (total, section) => total + section.question_count,
        0,
    );
    const answeredQuestionCount = Object.values(answers).filter(
        (answer) => answer.trim() !== '',
    ).length;
    const overallProgressPercentage =
        totalQuestions === 0
            ? 0
            : Math.round((answeredQuestionCount / totalQuestions) * 100);

    function updateAnswer(questionId: number, value: string) {
        setAnswers((currentAnswers) => ({
            ...currentAnswers,
            [questionId]: value,
        }));
    }

    return (
        <Form<{
            answers: Record<number, string>;
            resume: File | null;
        }>
            {...AssessmentController.store.form(campaign.id)}
            className="space-y-6"
        >
            {({ errors, processing, progress }) => {
                const formErrors = errors as Record<string, string | undefined>;

                return (
                    <>
                        <InputError message={formErrors.assessment} />

                        <section className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                            <div className="space-y-4">
                                <div className="flex items-start gap-3">
                                    <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-muted">
                                        <Upload className="size-4 text-muted-foreground" />
                                    </div>
                                    <div className="space-y-1">
                                        <h2 className="text-base font-medium">
                                            Resume PDF
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            Upload your resume as a PDF before
                                            submitting the assessment.
                                        </p>
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <label
                                        htmlFor="resume"
                                        className="text-sm font-medium"
                                    >
                                        Resume
                                    </label>
                                    <input
                                        id="resume"
                                        name="resume"
                                        type="file"
                                        accept="application/pdf,.pdf"
                                        required
                                        onChange={(event) =>
                                            setResumeName(
                                                event.currentTarget.files?.[0]
                                                    ?.name ?? '',
                                            )
                                        }
                                        className="flex min-h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm file:font-medium focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                    />
                                    {resumeName ? (
                                        <p className="text-xs text-muted-foreground">
                                            Selected: {resumeName}
                                        </p>
                                    ) : null}
                                    {progress ? (
                                        <div className="space-y-1">
                                            <progress
                                                value={progress.percentage}
                                                max="100"
                                                className="h-2 w-full"
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                Uploading {progress.percentage}%
                                            </p>
                                        </div>
                                    ) : null}
                                    <InputError message={formErrors.resume} />
                                </div>
                            </div>
                        </section>

                        <section className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                            <div className="space-y-3">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <h2 className="text-base font-medium">
                                            Exam progress
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            {answeredQuestionCount} of{' '}
                                            {totalQuestions} questions answered
                                        </p>
                                    </div>
                                    <p className="text-sm font-medium">
                                        {overallProgressPercentage}%
                                    </p>
                                </div>
                                <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full rounded-full bg-primary transition-all"
                                        style={{
                                            width: `${overallProgressPercentage}%`,
                                        }}
                                    />
                                </div>
                            </div>
                        </section>

                        <div className="space-y-4">
                            {sections.map((section, sectionIndex) => {
                                const answeredInSection =
                                    section.questions.filter(
                                        (question) =>
                                            answers[question.id]?.trim() !== '',
                                    ).length;
                                const sectionProgressPercentage =
                                    section.question_count === 0
                                        ? 0
                                        : Math.round(
                                              (answeredInSection /
                                                  section.question_count) *
                                                  100,
                                          );

                                return (
                                    <section
                                        key={section.id}
                                        className="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
                                    >
                                        <div className="space-y-4 border-b border-sidebar-border/70 p-5 dark:border-sidebar-border">
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div className="space-y-2">
                                                    <p className="text-xs font-medium text-muted-foreground">
                                                        Section{' '}
                                                        {sectionIndex + 1} of{' '}
                                                        {sections.length}
                                                    </p>
                                                    <div className="space-y-1">
                                                        <h2 className="text-base font-medium">
                                                            {section.title}
                                                        </h2>
                                                        {section.description ? (
                                                            <div className="space-y-1">
                                                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                                    Instruction
                                                                </p>
                                                                <p className="text-sm text-muted-foreground">
                                                                    {
                                                                        section.description
                                                                    }
                                                                </p>
                                                            </div>
                                                        ) : null}
                                                    </div>
                                                </div>
                                                <div className="flex flex-wrap items-center gap-2 text-xs font-medium text-muted-foreground">
                                                    <span className="rounded-full bg-muted px-2.5 py-1">
                                                        {section.question_count}{' '}
                                                        questions
                                                    </span>
                                                    {section.duration_minutes ? (
                                                        <span className="rounded-full bg-muted px-2.5 py-1">
                                                            {
                                                                section.duration_minutes
                                                            }{' '}
                                                            min
                                                        </span>
                                                    ) : null}
                                                </div>
                                            </div>
                                            <div className="space-y-2">
                                                <div className="flex items-center justify-between gap-3 text-sm">
                                                    <span className="text-muted-foreground">
                                                        Progress
                                                    </span>
                                                    <span className="font-medium">
                                                        {answeredInSection}/
                                                        {section.question_count}{' '}
                                                        answered
                                                    </span>
                                                </div>
                                                <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                                    <div
                                                        className="h-full rounded-full bg-primary transition-all"
                                                        style={{
                                                            width: `${sectionProgressPercentage}%`,
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div className="space-y-4 p-5">
                                            {section.questions.map(
                                                (question, index) => (
                                                    <section
                                                        key={question.id}
                                                        className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                                                    >
                                                        <div className="space-y-4">
                                                            <div className="space-y-1">
                                                                <p className="text-xs font-medium text-muted-foreground">
                                                                    Question{' '}
                                                                    {index + 1}
                                                                </p>
                                                                <div className="space-y-2">
                                                                    <h2 className="text-base font-medium">
                                                                        {
                                                                            question.content
                                                                        }
                                                                    </h2>
                                                                    <p className="text-xs text-muted-foreground">
                                                                        {
                                                                            question.type_label
                                                                        }{' '}
                                                                        -{' '}
                                                                        {
                                                                            question.points
                                                                        }{' '}
                                                                        pts
                                                                    </p>
                                                                </div>
                                                            </div>

                                                            <AnswerField
                                                                question={
                                                                    question
                                                                }
                                                                onAnswerChanged={
                                                                    updateAnswer
                                                                }
                                                                error={
                                                                    formErrors[
                                                                        `answers.${question.id}`
                                                                    ]
                                                                }
                                                            />
                                                        </div>
                                                    </section>
                                                ),
                                            )}
                                        </div>
                                    </section>
                                );
                            })}
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
                );
            }}
        </Form>
    );
}

function AnswerField({
    question,
    onAnswerChanged,
    error,
}: {
    question: Question;
    onAnswerChanged: (questionId: number, value: string) => void;
    error: string | undefined;
}) {
    const inputId = `answer-${question.id}`;
    const inputName = `answers[${question.id}]`;

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
                                name={inputName}
                                value={option}
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
                    name={inputName}
                    required
                    onChange={(event) =>
                        onAnswerChanged(question.id, event.currentTarget.value)
                    }
                    className="flex min-h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
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
                name={inputName}
                rows={question.type === 'short_text' ? 4 : 8}
                required
                onChange={(event) =>
                    onAnswerChanged(question.id, event.currentTarget.value)
                }
                className="flex min-h-40 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                placeholder={
                    question.type === 'matching_pairs'
                        ? 'Enter matching pairs, one per line.'
                        : 'Write your answer here.'
                }
            />
            <InputError message={error} />
        </div>
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
