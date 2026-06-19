import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowRightLeft,
    Plus,
    Sparkles,
    SquarePen,
    Trash2,
} from 'lucide-react';
import BankQuestionController, {
    convertToMcq,
    create as createBankQuestion,
    edit as editBankQuestion,
    regenerateMcqOptions,
} from '@/actions/App/Http/Controllers/Admin/BankQuestionController';
import QuestionBankQuestionGenerationController from '@/actions/App/Http/Controllers/Admin/QuestionBankQuestionGenerationController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import admin from '@/routes/admin';

const textareaClass =
    'flex min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50';

type GenerationAuditEntry = {
    generated_at: string;
    provider: string;
    model: string;
    prompt_version: string;
    agent: string;
    generation_options: {
        question_count?: number | null;
        language?: string | null;
        difficulty?: string | null;
        question_mix?: string | null;
    };
    questions_created: number;
};

type BankQuestion = {
    id: number;
    type: string;
    type_label: string;
    grading_mode: string;
    grading_mode_label: string;
    prompt: string;
    options: string[];
    correct_answer: string[];
    expected_rubric: string | null;
    points: number;
    difficulty: string;
    skill_tags: string[];
    ai_generated: boolean;
    status: string;
    status_label: string;
    sort_order: number;
    campaign_imports_count: number;
};

type QuestionBank = {
    id: number;
    title: string;
    description: string | null;
    skill_area: string | null;
    difficulty: string;
    is_active: boolean;
    ai_generation_audit: GenerationAuditEntry[];
    created_by: string | null;
    created_at: string;
    questions: BankQuestion[];
};

type Props = {
    questionBank: QuestionBank;
};

type GenerateQuestionsFormData = {
    question_count: number;
    language: string;
    difficulty: string;
    question_mix: string;
    generation: string;
};

export default function AdminQuestionBanksShow({ questionBank }: Props) {
    return (
        <>
            <Head title={questionBank.title} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={questionBank.title}
                        description={
                            questionBank.skill_area ||
                            'Reusable assessment library'
                        }
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button asChild>
                            <Link href={createBankQuestion(questionBank.id)}>
                                <Plus />
                                Add question
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link
                                href={admin.questionBanks.edit(questionBank.id)}
                            >
                                Edit library
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={admin.questionBanks.index()}>
                                Back to libraries
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-4">
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">Status</p>
                        <Badge
                            className="mt-2"
                            variant={
                                questionBank.is_active ? 'default' : 'secondary'
                            }
                        >
                            {questionBank.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                    </div>
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            Difficulty
                        </p>
                        <p className="mt-2 text-2xl font-semibold capitalize">
                            {questionBank.difficulty}
                        </p>
                    </div>
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            Questions
                        </p>
                        <p className="mt-2 text-2xl font-semibold">
                            {questionBank.questions.length}
                        </p>
                    </div>
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            Created by
                        </p>
                        <p className="mt-2 font-medium">
                            {questionBank.created_by || '-'}
                        </p>
                    </div>
                </div>

                <section className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                    <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
                        <div className="space-y-4">
                            <h2 className="text-base font-medium">
                                Library context
                            </h2>
                            <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                {questionBank.description || '-'}
                            </p>

                            {questionBank.ai_generation_audit.length > 0 ? (
                                <div>
                                    <h3 className="text-sm font-medium">
                                        Generation audit
                                    </h3>
                                    <ul className="mt-2 space-y-2">
                                        {questionBank.ai_generation_audit.map(
                                            (entry, index) => (
                                                <li
                                                    key={`${entry.generated_at}-${index}`}
                                                    className="rounded-md border border-sidebar-border/70 p-3 text-xs text-muted-foreground dark:border-sidebar-border"
                                                >
                                                    Run {index + 1}:{' '}
                                                    {entry.questions_created}{' '}
                                                    questions · {entry.model} ·
                                                    prompt v
                                                    {entry.prompt_version}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </div>
                            ) : null}
                        </div>

                        <section className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <h2 className="text-base font-medium">
                                Generate questions
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Add AI draft questions to this library without
                                duplicating existing prompts.
                            </p>
                            <Form<GenerateQuestionsFormData>
                                {...QuestionBankQuestionGenerationController.store.form(
                                    questionBank.id,
                                )}
                                options={{
                                    preserveScroll: true,
                                }}
                                className="mt-4 space-y-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="grid grid-cols-2 gap-3">
                                            <div className="grid gap-2">
                                                <Label htmlFor="generate-question-count">
                                                    Questions
                                                </Label>
                                                <Input
                                                    id="generate-question-count"
                                                    name="question_count"
                                                    type="number"
                                                    min={1}
                                                    max={20}
                                                    defaultValue={4}
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors.question_count
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label>Difficulty</Label>
                                                <Select
                                                    name="difficulty"
                                                    defaultValue={
                                                        questionBank.difficulty
                                                    }
                                                >
                                                    <SelectTrigger className="w-full">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="mixed">
                                                            Mixed
                                                        </SelectItem>
                                                        <SelectItem value="easy">
                                                            Easy
                                                        </SelectItem>
                                                        <SelectItem value="medium">
                                                            Medium
                                                        </SelectItem>
                                                        <SelectItem value="hard">
                                                            Hard
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={errors.difficulty}
                                                />
                                            </div>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="generate-language">
                                                Language
                                            </Label>
                                            <Input
                                                id="generate-language"
                                                name="language"
                                                defaultValue="English"
                                                required
                                            />
                                            <InputError
                                                message={errors.language}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="question_mix">
                                                Question mix
                                            </Label>
                                            <textarea
                                                id="question_mix"
                                                name="question_mix"
                                                rows={3}
                                                className={textareaClass}
                                                placeholder="2 MCQ, 1 short text, 1 long text"
                                            />
                                            <InputError
                                                message={errors.question_mix}
                                            />
                                        </div>
                                        <InputError
                                            message={errors.generation}
                                        />
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="w-full"
                                        >
                                            {processing && <Spinner />}
                                            <Sparkles />
                                            Generate more
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </section>
                    </div>
                </section>

                <section className="space-y-4">
                    <div className="flex items-center justify-between gap-4">
                        <h2 className="text-base font-medium">Questions</h2>
                    </div>

                    {questionBank.questions.length === 0 ? (
                        <div className="rounded-lg border border-sidebar-border/70 p-8 text-center dark:border-sidebar-border">
                            <h3 className="text-base font-medium">
                                No reusable questions yet
                            </h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Add questions manually now, then import them
                                into campaign sections later.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                            <div className="divide-y">
                                {questionBank.questions.map((question) => (
                                    <div
                                        key={question.id}
                                        className="grid gap-4 p-4 lg:grid-cols-[1fr_200px]"
                                    >
                                        <div className="space-y-2">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge variant="secondary">
                                                    {question.type_label}
                                                </Badge>
                                                <Badge variant="outline">
                                                    {
                                                        question.grading_mode_label
                                                    }
                                                </Badge>
                                                <Badge variant="outline">
                                                    {question.points} pts
                                                </Badge>
                                                <Badge
                                                    variant={
                                                        question.status ===
                                                        'approved'
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                >
                                                    {question.status_label}
                                                </Badge>
                                                <span className="text-xs text-muted-foreground">
                                                    {question.difficulty}
                                                </span>
                                                {question.ai_generated ? (
                                                    <Badge variant="outline">
                                                        AI draft
                                                    </Badge>
                                                ) : null}
                                            </div>
                                            <p className="font-medium">
                                                {question.prompt}
                                            </p>
                                            {question.skill_tags.length > 0 ? (
                                                <div className="flex flex-wrap gap-2">
                                                    {question.skill_tags.map(
                                                        (tag) => (
                                                            <Badge
                                                                key={tag}
                                                                variant="outline"
                                                            >
                                                                {tag}
                                                            </Badge>
                                                        ),
                                                    )}
                                                </div>
                                            ) : null}
                                            {question.expected_rubric ? (
                                                <p className="line-clamp-2 text-sm text-muted-foreground">
                                                    {question.expected_rubric}
                                                </p>
                                            ) : null}
                                            <p className="text-xs text-muted-foreground">
                                                Imported into{' '}
                                                {
                                                    question.campaign_imports_count
                                                }{' '}
                                                campaign question
                                                {question.campaign_imports_count ===
                                                1
                                                    ? ''
                                                    : 's'}
                                            </p>
                                        </div>

                                        <div className="flex flex-col items-end justify-start gap-2">
                                            {question.status === 'draft' &&
                                            question.type ===
                                                'multiple_choice' ? (
                                                <Form
                                                    {...regenerateMcqOptions.form(
                                                        [
                                                            questionBank.id,
                                                            question.id,
                                                        ],
                                                    )}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                >
                                                    {({
                                                        processing,
                                                        errors,
                                                    }) => (
                                                        <>
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                variant="secondary"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                {processing && (
                                                                    <Spinner />
                                                                )}
                                                                <Sparkles />
                                                                Regenerate
                                                                options
                                                            </Button>
                                                            <InputError
                                                                message={
                                                                    errors.regeneration
                                                                }
                                                            />
                                                        </>
                                                    )}
                                                </Form>
                                            ) : null}
                                            {question.status === 'draft' &&
                                            (question.type === 'short_text' ||
                                                question.type ===
                                                    'long_text') ? (
                                                <Form
                                                    {...convertToMcq.form([
                                                        questionBank.id,
                                                        question.id,
                                                    ])}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                >
                                                    {({
                                                        processing,
                                                        errors,
                                                    }) => (
                                                        <>
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                variant="outline"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                {processing && (
                                                                    <Spinner />
                                                                )}
                                                                <ArrowRightLeft />
                                                                Convert to MCQ
                                                            </Button>
                                                            <InputError
                                                                message={
                                                                    errors.conversion
                                                                }
                                                            />
                                                        </>
                                                    )}
                                                </Form>
                                            ) : null}
                                            <div className="flex gap-2">
                                                <Button
                                                    size="icon"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link
                                                        href={editBankQuestion([
                                                            questionBank.id,
                                                            question.id,
                                                        ])}
                                                        aria-label="Edit question"
                                                    >
                                                        <SquarePen />
                                                    </Link>
                                                </Button>
                                                <Form
                                                    {...BankQuestionController.destroy.form.delete(
                                                        [
                                                            questionBank.id,
                                                            question.id,
                                                        ],
                                                    )}
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            size="icon"
                                                            variant="outline"
                                                            disabled={
                                                                processing
                                                            }
                                                            aria-label="Delete question"
                                                        >
                                                            <Trash2 />
                                                        </Button>
                                                    )}
                                                </Form>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

AdminQuestionBanksShow.layout = {
    breadcrumbs: [
        {
            title: 'Question Libraries',
            href: admin.questionBanks.index(),
        },
        {
            title: 'Detail',
            href: admin.questionBanks.show(0),
        },
    ],
};
