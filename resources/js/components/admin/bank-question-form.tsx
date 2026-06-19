import { Form, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import type { RouteFormDefinition } from '@/wayfinder';

export type BankQuestionFormValues = {
    id?: number;
    type: string;
    grading_mode: string;
    prompt: string;
    options: string[];
    correct_answer: string[];
    expected_rubric: string | null;
    points: number;
    difficulty: string;
    skill_tags: string[];
    ai_generated: boolean;
    sort_order: number;
};

type Option = {
    value: string;
    label: string;
};

type QuestionTypeOption = Option & {
    deterministic: boolean;
};

type BankQuestionFormData = {
    type: string;
    grading_mode: string;
    prompt: string;
    options_text: string;
    correct_answer_text: string;
    expected_rubric: string;
    points: number;
    difficulty: string;
    skill_tags_text: string;
    ai_generated: boolean;
    sort_order: number;
};

type Props = {
    action: RouteFormDefinition<'post'>;
    cancelHref: string;
    submitLabel: string;
    question?: BankQuestionFormValues;
    questionTypes: QuestionTypeOption[];
    gradingModeOptions: Option[];
    difficultyOptions: Option[];
};

const textareaClass =
    'flex min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50';

export default function BankQuestionForm({
    action,
    cancelHref,
    submitLabel,
    question,
    questionTypes,
    gradingModeOptions,
    difficultyOptions,
}: Props) {
    return (
        <Form<BankQuestionFormData>
            {...action}
            options={{
                preserveScroll: true,
            }}
            className="space-y-6"
        >
            {({ errors, processing }) => (
                <>
                    <div className="grid gap-6 sm:grid-cols-[1fr_180px_180px_160px]">
                        <div className="grid gap-2">
                            <Label>Type</Label>
                            <Select
                                name="type"
                                defaultValue={question?.type ?? 'long_text'}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {questionTypes.map((type) => (
                                        <SelectItem
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.type} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Grading mode</Label>
                            <Select
                                name="grading_mode"
                                defaultValue={question?.grading_mode ?? 'ai'}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {gradingModeOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.grading_mode} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Difficulty</Label>
                            <Select
                                name="difficulty"
                                defaultValue={question?.difficulty ?? 'medium'}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {difficultyOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.difficulty} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="points">Points</Label>
                            <Input
                                id="points"
                                name="points"
                                type="number"
                                min={1}
                                defaultValue={question?.points ?? 10}
                                required
                            />
                            <InputError message={errors.points} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="prompt">Prompt</Label>
                        <textarea
                            id="prompt"
                            name="prompt"
                            defaultValue={question?.prompt}
                            required
                            rows={5}
                            className={textareaClass}
                            placeholder="Write the reusable assessment question."
                        />
                        <InputError message={errors.prompt} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="expected_rubric">Rubric</Label>
                        <textarea
                            id="expected_rubric"
                            name="expected_rubric"
                            defaultValue={question?.expected_rubric ?? ''}
                            rows={5}
                            className={textareaClass}
                            placeholder="Required for short/long text questions."
                        />
                        <InputError message={errors.expected_rubric} />
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="options_text">Options</Label>
                            <textarea
                                id="options_text"
                                name="options_text"
                                defaultValue={question?.options.join('\n')}
                                rows={5}
                                className={textareaClass}
                                placeholder="One option per line."
                            />
                            <InputError message={errors.options_text} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="correct_answer_text">
                                Correct answer
                            </Label>
                            <textarea
                                id="correct_answer_text"
                                name="correct_answer_text"
                                defaultValue={question?.correct_answer.join(
                                    '\n',
                                )}
                                rows={5}
                                className={textareaClass}
                                placeholder="One accepted answer per line."
                            />
                            <InputError message={errors.correct_answer_text} />
                        </div>
                    </div>

                    <div className="grid gap-6 sm:grid-cols-[1fr_160px]">
                        <div className="grid gap-2">
                            <Label htmlFor="skill_tags_text">Skill tags</Label>
                            <textarea
                                id="skill_tags_text"
                                name="skill_tags_text"
                                defaultValue={question?.skill_tags.join('\n')}
                                rows={4}
                                className={textareaClass}
                                placeholder="Laravel&#10;Queues&#10;PostgreSQL"
                            />
                            <InputError message={errors.skill_tags_text} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="sort_order">Order</Label>
                            <Input
                                id="sort_order"
                                name="sort_order"
                                type="number"
                                min={0}
                                defaultValue={question?.sort_order ?? 10}
                                required
                            />
                            <InputError message={errors.sort_order} />
                        </div>
                    </div>

                    <div className="flex items-center gap-3 rounded-md border border-border px-3 py-2.5">
                        <input type="hidden" name="ai_generated" value="0" />
                        <Checkbox
                            id="ai_generated"
                            name="ai_generated"
                            value="1"
                            defaultChecked={question?.ai_generated ?? false}
                        />
                        <Label
                            htmlFor="ai_generated"
                            className="text-sm font-normal"
                        >
                            AI-generated draft
                        </Label>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {submitLabel}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href={cancelHref}>Cancel</Link>
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
