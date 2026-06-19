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
import admin from '@/routes/admin';
import type { RouteFormDefinition } from '@/wayfinder';

export type QuestionBankFormValues = {
    id?: number;
    title: string;
    description: string | null;
    skill_area: string | null;
    difficulty: string;
    is_active: boolean;
};

type DifficultyOption = {
    value: string;
    label: string;
};

type QuestionBankFormData = {
    title: string;
    description: string;
    skill_area: string;
    difficulty: string;
    is_active: boolean;
};

type Props = {
    action: RouteFormDefinition<'post'>;
    submitLabel: string;
    questionBank?: QuestionBankFormValues;
    difficultyOptions: DifficultyOption[];
};

const textareaClass =
    'flex min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50';

export default function QuestionBankForm({
    action,
    submitLabel,
    questionBank,
    difficultyOptions,
}: Props) {
    return (
        <Form<QuestionBankFormData>
            {...action}
            options={{
                preserveScroll: true,
            }}
            className="space-y-6"
        >
            {({ errors, processing }) => (
                <>
                    <div className="grid gap-6 sm:grid-cols-[1fr_220px]">
                        <div className="grid gap-2">
                            <Label htmlFor="title">Library title</Label>
                            <Input
                                id="title"
                                name="title"
                                defaultValue={questionBank?.title}
                                required
                                placeholder="Laravel Backend - Mid Level"
                            />
                            <InputError message={errors.title} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Difficulty</Label>
                            <Select
                                name="difficulty"
                                defaultValue={
                                    questionBank?.difficulty ?? 'medium'
                                }
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
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="skill_area">Skill area</Label>
                        <Input
                            id="skill_area"
                            name="skill_area"
                            defaultValue={questionBank?.skill_area ?? ''}
                            placeholder="Laravel, PostgreSQL, System Design"
                        />
                        <InputError message={errors.skill_area} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <textarea
                            id="description"
                            name="description"
                            defaultValue={questionBank?.description ?? ''}
                            rows={5}
                            className={textareaClass}
                            placeholder="Scope, intended seniority, and reuse notes."
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="flex items-center gap-3 rounded-md border border-border px-3 py-2.5">
                        <input type="hidden" name="is_active" value="0" />
                        <Checkbox
                            id="is_active"
                            name="is_active"
                            value="1"
                            defaultChecked={questionBank?.is_active ?? true}
                        />
                        <Label
                            htmlFor="is_active"
                            className="text-sm font-normal"
                        >
                            Available for campaign import
                        </Label>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {submitLabel}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href={admin.questionBanks.index()}>
                                Cancel
                            </Link>
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
