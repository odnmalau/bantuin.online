import { Form, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
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
import type { RouteFormDefinition } from '@/wayfinder';

export type CampaignRankingWeights = {
    resume_score: number;
    essay_score: number;
    mcq_score: number;
};

export type CampaignFormValues = {
    id?: number;
    title: string;
    role_title: string;
    seniority: string | null;
    job_description: string | null;
    required_skills: string[];
    nice_to_have_skills: string[];
    language: string | null;
    threshold_score: number;
    ranking_weights: CampaignRankingWeights;
    status: string;
    ai_generation_notes: string | null;
};

type Option = {
    value: string;
    label: string;
};

type CampaignFormData = {
    title: string;
    role_title: string;
    seniority: string;
    job_description: string;
    required_skills: string;
    nice_to_have_skills: string;
    language: string;
    threshold_score: number;
    status: string;
    ai_generation_notes: string;
};

type Props = {
    action: RouteFormDefinition<'post'>;
    submitLabel: string;
    campaign?: CampaignFormValues;
    statusOptions: Option[];
    defaultRankingWeights?: CampaignRankingWeights;
};

const textareaClass =
    'flex min-h-32 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50';

function resolveRankingWeights(
    campaign?: CampaignFormValues,
    defaultRankingWeights?: CampaignRankingWeights,
): CampaignRankingWeights {
    if (campaign?.ranking_weights) {
        return campaign.ranking_weights;
    }

    if (defaultRankingWeights) {
        return defaultRankingWeights;
    }

    return {
        resume_score: 35,
        essay_score: 50,
        mcq_score: 15,
    };
}

export default function CampaignForm({
    action,
    submitLabel,
    campaign,
    statusOptions,
    defaultRankingWeights,
}: Props) {
    const rankingWeights = resolveRankingWeights(
        campaign,
        defaultRankingWeights,
    );

    return (
        <Form<CampaignFormData>
            {...action}
            options={{
                preserveScroll: true,
            }}
            className="space-y-6"
        >
            {({ errors, processing }) => {
                const fieldErrors = errors as Record<
                    string,
                    string | undefined
                >;

                return (
                    <>
                        <div className="grid gap-6 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="title">Campaign title</Label>
                                <Input
                                    id="title"
                                    name="title"
                                    defaultValue={campaign?.title}
                                    required
                                    placeholder="Backend Engineer Screening"
                                />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="role_title">Role title</Label>
                                <Input
                                    id="role_title"
                                    name="role_title"
                                    defaultValue={campaign?.role_title}
                                    required
                                    placeholder="Backend Engineer"
                                />
                                <InputError message={errors.role_title} />
                            </div>
                        </div>

                        <div className="grid gap-6 sm:grid-cols-[1fr_180px_180px]">
                            <div className="grid gap-2">
                                <Label htmlFor="seniority">Seniority</Label>
                                <Input
                                    id="seniority"
                                    name="seniority"
                                    defaultValue={campaign?.seniority ?? ''}
                                    placeholder="Mid-level"
                                />
                                <InputError message={errors.seniority} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="threshold_score">
                                    Threshold
                                </Label>
                                <Input
                                    id="threshold_score"
                                    name="threshold_score"
                                    type="number"
                                    min={0}
                                    max={100}
                                    defaultValue={
                                        campaign?.threshold_score ?? 75
                                    }
                                    required
                                />
                                <InputError message={errors.threshold_score} />
                                <p className="text-sm text-muted-foreground">
                                    Minimum score for pending approval after AI
                                    evaluation for candidates taking this
                                    campaign.
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label>Status</Label>
                                <Select
                                    name="status"
                                    defaultValue={campaign?.status ?? 'draft'}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {statusOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.status} />
                            </div>
                        </div>

                        <div className="grid gap-2 sm:max-w-xs">
                            <Label htmlFor="language">
                                Assessment language
                            </Label>
                            <Input
                                id="language"
                                name="language"
                                defaultValue={campaign?.language ?? 'English'}
                                required
                                placeholder="English"
                            />
                            <p className="text-xs text-muted-foreground">
                                Used for AI-generated questions and resume
                                screening context.
                            </p>
                            <InputError message={errors.language} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="required_skills">
                                Required skills
                            </Label>
                            <textarea
                                id="required_skills"
                                name="required_skills"
                                defaultValue={
                                    campaign?.required_skills?.join('\n') ?? ''
                                }
                                rows={4}
                                className={textareaClass}
                                placeholder="Laravel&#10;PostgreSQL&#10;Queue workers"
                            />
                            <InputError message={errors.required_skills} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="nice_to_have_skills">
                                Nice-to-have skills
                            </Label>
                            <textarea
                                id="nice_to_have_skills"
                                name="nice_to_have_skills"
                                defaultValue={campaign?.nice_to_have_skills?.join(
                                    '\n',
                                )}
                                rows={3}
                                className={textareaClass}
                                placeholder="Redis&#10;Docker"
                            />
                            <InputError message={errors.nice_to_have_skills} />
                        </div>

                        <fieldset className="grid gap-4 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <legend className="px-1 text-sm font-medium">
                                Ranking weights
                            </legend>
                            <p className="text-xs text-muted-foreground">
                                Resume, essay, and MCQ weights must total 100.
                                These drive the backend ranking formula for this
                                campaign.
                            </p>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="ranking_weights_resume_score">
                                        Resume %
                                    </Label>
                                    <Input
                                        id="ranking_weights_resume_score"
                                        name="ranking_weights[resume_score]"
                                        type="number"
                                        min={0}
                                        max={100}
                                        defaultValue={
                                            rankingWeights.resume_score
                                        }
                                        required
                                    />
                                    <InputError
                                        message={
                                            fieldErrors[
                                                'ranking_weights.resume_score'
                                            ]
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="ranking_weights_essay_score">
                                        Essay %
                                    </Label>
                                    <Input
                                        id="ranking_weights_essay_score"
                                        name="ranking_weights[essay_score]"
                                        type="number"
                                        min={0}
                                        max={100}
                                        defaultValue={
                                            rankingWeights.essay_score
                                        }
                                        required
                                    />
                                    <InputError
                                        message={
                                            fieldErrors[
                                                'ranking_weights.essay_score'
                                            ]
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="ranking_weights_mcq_score">
                                        MCQ %
                                    </Label>
                                    <Input
                                        id="ranking_weights_mcq_score"
                                        name="ranking_weights[mcq_score]"
                                        type="number"
                                        min={0}
                                        max={100}
                                        defaultValue={rankingWeights.mcq_score}
                                        required
                                    />
                                    <InputError
                                        message={
                                            fieldErrors[
                                                'ranking_weights.mcq_score'
                                            ]
                                        }
                                    />
                                </div>
                            </div>
                            <InputError message={fieldErrors.ranking_weights} />
                        </fieldset>

                        <div className="grid gap-2">
                            <Label htmlFor="job_description">
                                Job description
                            </Label>
                            <textarea
                                id="job_description"
                                name="job_description"
                                defaultValue={campaign?.job_description ?? ''}
                                rows={8}
                                className={textareaClass}
                                placeholder="Paste the role context or hiring brief."
                            />
                            <InputError message={errors.job_description} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="ai_generation_notes">
                                AI generation notes
                            </Label>
                            <textarea
                                id="ai_generation_notes"
                                name="ai_generation_notes"
                                defaultValue={
                                    campaign?.ai_generation_notes ?? ''
                                }
                                rows={5}
                                className={textareaClass}
                                placeholder="Constraints, must-cover topics, or calibration notes."
                            />
                            <InputError message={errors.ai_generation_notes} />
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                {submitLabel}
                            </Button>
                            <Button type="button" variant="outline" asChild>
                                <Link href={admin.campaigns.index()}>
                                    Cancel
                                </Link>
                            </Button>
                        </div>
                    </>
                );
            }}
        </Form>
    );
}
