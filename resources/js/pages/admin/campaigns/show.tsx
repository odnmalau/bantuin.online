import { Deferred, Form, Head, usePage } from '@inertiajs/react';
import {
    Check,
    ChevronRight,
    Mail,
    MoreHorizontal,
    Plus,
    Rocket,
    Share2,
    Sparkles,
} from 'lucide-react';
import { useState } from 'react';
import CampaignAssessmentGenerationController from '@/actions/App/Http/Controllers/Admin/CampaignAssessmentGenerationController';
import CampaignController from '@/actions/App/Http/Controllers/Admin/CampaignController';
import CampaignQuestionController from '@/actions/App/Http/Controllers/Admin/CampaignQuestionController';
import CampaignRankingController from '@/actions/App/Http/Controllers/Admin/CampaignRankingController';
import CampaignSectionController from '@/actions/App/Http/Controllers/Admin/CampaignSectionController';
import CampaignStatusController from '@/actions/App/Http/Controllers/Admin/CampaignStatusController';
import CampaignForm from '@/components/admin/campaign-form';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
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
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import admin from '@/routes/admin';
import type { SharedData } from '@/types';

type CampaignQuestion = {
    id: number;
    campaign_section_id: number;
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
    is_required: boolean;
    sort_order: number;
};

type CampaignSection = {
    id: number;
    title: string;
    description: string | null;
    duration_minutes: number | null;
    scoring_mode: string;
    weight: number;
    sort_order: number;
    questions: CampaignQuestion[];
};

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
    sections_created: number;
    questions_created: number;
};

type Campaign = {
    id: number;
    title: string;
    role_title: string;
    seniority: string | null;
    job_description: string | null;
    required_skills: string[];
    language: string;
    threshold_score: number;
    ranking_weights: {
        resume_score: number;
        essay_score: number;
        mcq_score: number;
    };
    ranking_weights_configured: boolean;
    status: string;
    status_label: string;
    ai_generation_audit: GenerationAuditEntry[];
    created_by: string | null;
    created_at: string;
    activated_at: string | null;
    draft_questions_count: number;
    approved_questions_count: number;
    can_publish: boolean;
    sections: CampaignSection[];
};

type QuestionTypeOption = {
    value: string;
    label: string;
    deterministic: boolean;
};

type GradingModeOption = {
    value: string;
    label: string;
};

type CampaignInvitationRow = {
    id: number;
    email: string;
    status: string;
    status_label: string;
    sent_at: string | null;
    accepted_at: string | null;
    expires_at: string | null;
    invite_url: string | null;
};

type Props = {
    campaign?: Campaign;
    invitations?: CampaignInvitationRow[];
    questionTypes: QuestionTypeOption[];
    gradingModeOptions: GradingModeOption[];
};

type SectionFormData = {
    title: string;
    description: string;
    duration_minutes: number;
    scoring_mode: string;
    weight: number;
    sort_order: number;
};

type QuestionFormData = {
    campaign_section_id: number;
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
    is_required: boolean;
    sort_order: number;
};

type GenerateAssessmentFormData = {
    question_count: number;
    language: string;
    difficulty: string;
    question_mix: string;
    generation: string;
};

type RankingWeightsFormData = {
    ranking_weights: {
        resume_score: number;
        essay_score: number;
        mcq_score: number;
    };
};

const textareaClass =
    'flex min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50';

function CampaignDetailSkeleton() {
    return (
        <div className="flex flex-col gap-6">
            <Card className="gap-0">
                <CardHeader>
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <Skeleton className="h-6 w-64" />
                        <div className="flex flex-wrap items-center gap-2 md:justify-end">
                            <Skeleton className="h-8 w-24" />
                            <Skeleton className="h-8 w-24" />
                            <Skeleton className="size-8" />
                        </div>
                    </div>
                </CardHeader>
            </Card>

            <Card className="gap-0">
                <CardHeader className="border-b">
                    <Skeleton className="h-5 w-48" />
                    <div className="flex flex-wrap items-center gap-2">
                        <Skeleton className="h-8 w-36" />
                        <Skeleton className="h-8 w-40" />
                    </div>
                </CardHeader>
                <CardContent className="flex flex-col gap-6 p-(--card-spacing)">
                    {Array.from({ length: 2 }).map((_, index) => (
                        <div key={index} className="flex flex-col gap-4">
                            <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div className="flex flex-col gap-2">
                                    <Skeleton className="h-5 w-40" />
                                    <Skeleton className="h-4 w-56" />
                                </div>
                                <Skeleton className="size-8" />
                            </div>
                            <div className="flex flex-col gap-3">
                                {Array.from({ length: 2 }).map(
                                    (__, questionIndex) => (
                                        <div
                                            key={questionIndex}
                                            className="rounded-lg border border-sidebar-border/70 p-4"
                                        >
                                            <Skeleton className="h-4 w-3/4" />
                                            <Skeleton className="mt-3 h-4 w-1/2" />
                                            <div className="mt-4 flex flex-wrap gap-2">
                                                <Skeleton className="h-6 w-16" />
                                                <Skeleton className="h-6 w-20" />
                                                <Skeleton className="h-6 w-14" />
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                        </div>
                    ))}
                </CardContent>
                <CardFooter className="-mb-(--card-spacing) justify-between gap-3 border-t bg-background py-(--card-spacing)">
                    <Skeleton className="h-4 w-72" />
                    <Skeleton className="h-8 w-28" />
                </CardFooter>
            </Card>

            <Card className="gap-0">
                <CardHeader>
                    <Skeleton className="h-5 w-40" />
                </CardHeader>
            </Card>
        </div>
    );
}

export default function AdminCampaignsShow({
    campaign,
    invitations,
    questionTypes,
    gradingModeOptions,
}: Props) {
    const page = usePage<
        SharedData & { flash?: { campaign_invite_url?: string } }
    >();
    const latestInviteUrl = page.props.flash?.campaign_invite_url;
    const { auth } = page.props;
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);

    return (
        <>
            <Head title={campaign?.title ?? 'Campaign'} />

            <div
                className={`flex flex-col gap-6 p-4 ${auth.readOnly ? '[&_form]:pointer-events-none [&_form]:opacity-60' : ''}`}
            >
                {auth.readOnly ? (
                    <Card className="border-amber-500/30 bg-amber-500/5">
                        <CardContent className="text-sm text-muted-foreground">
                            This Team is deactivated. Campaign history is
                            read-only until the Team is reactivated.
                        </CardContent>
                    </Card>
                ) : null}
                <Deferred
                    data={['campaign', 'invitations']}
                    fallback={<CampaignDetailSkeleton />}
                >
                    {campaign !== undefined && invitations !== undefined ? (
                        <>
                            <CampaignOverviewCard
                                campaign={campaign}
                                invitations={invitations}
                                latestInviteUrl={latestInviteUrl}
                                deleteDialogOpen={deleteDialogOpen}
                                onDeleteDialogOpenChange={setDeleteDialogOpen}
                            />

                            <div className="flex flex-col gap-6">
                                <Card className="gap-0">
                                    <CardHeader className="border-b">
                                        <CardTitle>
                                            Sections and questions
                                        </CardTitle>
                                        <CardAction className="flex flex-wrap items-center gap-2">
                                            <GenerateAssessmentDialog
                                                campaignId={campaign.id}
                                            />
                                            {campaign.draft_questions_count >
                                            0 ? (
                                                <Form
                                                    {...CampaignQuestionController.approveAll.form(
                                                        campaign.id,
                                                    )}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            variant="outline"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            {processing && (
                                                                <Spinner />
                                                            )}
                                                            <Check data-icon="inline-start" />
                                                            Approve all drafts
                                                        </Button>
                                                    )}
                                                </Form>
                                            ) : null}
                                        </CardAction>
                                    </CardHeader>

                                    <CardContent className="p-0">
                                        {campaign.sections.length === 0 ? (
                                            <p className="p-(--card-spacing) text-sm text-muted-foreground">
                                                No sections yet.
                                            </p>
                                        ) : (
                                            <div className="divide-y">
                                                {campaign.sections.map(
                                                    (section) => (
                                                        <section
                                                            key={section.id}
                                                            className="flex flex-col gap-4 p-(--card-spacing)"
                                                        >
                                                            <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                                                <div className="flex flex-col gap-2">
                                                                    <div className="flex flex-wrap items-center gap-2">
                                                                        <h3 className="text-base font-medium">
                                                                            {
                                                                                section.title
                                                                            }
                                                                        </h3>
                                                                        <Badge variant="outline">
                                                                            {
                                                                                section.weight
                                                                            }{' '}
                                                                            weight
                                                                        </Badge>
                                                                        {section.duration_minutes ? (
                                                                            <Badge variant="secondary">
                                                                                {
                                                                                    section.duration_minutes
                                                                                }{' '}
                                                                                min
                                                                            </Badge>
                                                                        ) : null}
                                                                    </div>
                                                                    {section.description ? (
                                                                        <p className="text-sm text-muted-foreground">
                                                                            {
                                                                                section.description
                                                                            }
                                                                        </p>
                                                                    ) : null}
                                                                </div>

                                                                <div className="flex flex-wrap items-center gap-2 md:justify-end">
                                                                    <SectionActionsDropdown
                                                                        campaignId={
                                                                            campaign.id
                                                                        }
                                                                        section={
                                                                            section
                                                                        }
                                                                        questionTypes={
                                                                            questionTypes
                                                                        }
                                                                        gradingModeOptions={
                                                                            gradingModeOptions
                                                                        }
                                                                    />
                                                                </div>
                                                            </div>

                                            {section.questions.length === 0 ? (
                                                <p className="text-sm text-muted-foreground">
                                                    No questions in this
                                                    section.
                                                </p>
                                            ) : (
                                                <div className="divide-y rounded-md border">
                                                    {section.questions.map(
                                                        (question) => (
                                                            <div
                                                                key={
                                                                    question.id
                                                                }
                                                                className="grid gap-4 p-4 lg:grid-cols-[1fr_160px]"
                                                            >
                                                                <div className="flex flex-col gap-2">
                                                                    <div className="flex flex-wrap items-center gap-2">
                                                                        <Badge variant="secondary">
                                                                            {
                                                                                question.type_label
                                                                            }
                                                                        </Badge>
                                                                        <Badge variant="outline">
                                                                            {
                                                                                question.grading_mode_label
                                                                            }
                                                                        </Badge>
                                                                        <Badge variant="outline">
                                                                            {
                                                                                question.points
                                                                            }{' '}
                                                                            pts
                                                                        </Badge>
                                                                        <Badge
                                                                            variant={
                                                                                question.status ===
                                                                                'approved'
                                                                                    ? 'default'
                                                                                    : 'outline'
                                                                            }
                                                                        >
                                                                            {
                                                                                question.status_label
                                                                            }
                                                                        </Badge>
                                                                        <span className="text-xs text-muted-foreground">
                                                                            {
                                                                                question.difficulty
                                                                            }
                                                                        </span>
                                                                    </div>
                                                                    <p className="font-medium">
                                                                        {
                                                                            question.prompt
                                                                        }
                                                                    </p>
                                                                    {question
                                                                        .skill_tags
                                                                        .length >
                                                                    0 ? (
                                                                        <div className="flex flex-wrap gap-2">
                                                                            {question.skill_tags.map(
                                                                                (
                                                                                    tag,
                                                                                ) => (
                                                                                    <Badge
                                                                                        key={
                                                                                            tag
                                                                                        }
                                                                                        variant="outline"
                                                                                    >
                                                                                        {
                                                                                            tag
                                                                                        }
                                                                                    </Badge>
                                                                                ),
                                                                            )}
                                                                        </div>
                                                                    ) : null}
                                                                    {question.expected_rubric ? (
                                                                        <p className="line-clamp-2 text-sm text-muted-foreground">
                                                                            {
                                                                                question.expected_rubric
                                                                            }
                                                                        </p>
                                                                    ) : null}
                                                                </div>
                                                                <div className="flex justify-end">
                                                                    <QuestionActionsDropdown
                                                                        campaignId={
                                                                            campaign.id
                                                                        }
                                                                        question={
                                                                            question
                                                                        }
                                                                        sections={
                                                                            campaign.sections
                                                                        }
                                                                        questionTypes={
                                                                            questionTypes
                                                                        }
                                                                        gradingModeOptions={
                                                                            gradingModeOptions
                                                                        }
                                                                    />
                                                                </div>
                                                            </div>
                                                        ),
                                                    )}
                                                </div>
                                            )}
                                        </section>
                                    ))}
                                </div>
                            )}
                        </CardContent>

                        <CardFooter className="-mb-(--card-spacing) justify-between gap-3 border-t bg-background py-(--card-spacing)">
                            <p className="text-sm text-muted-foreground">
                                Add another section when the assessment needs a
                                separate topic or scoring group.
                            </p>
                            <AddSectionSheet campaignId={campaign.id} />
                        </CardFooter>
                    </Card>
                </div>

                <Collapsible className="group/collapsible">
                    <Card className="gap-0">
                        <CollapsibleTrigger asChild>
                            <CardHeader className="cursor-pointer">
                                <CardTitle className="flex items-center gap-2">
                                    <ChevronRight className="size-4 shrink-0 text-muted-foreground transition-transform group-data-[state=open]/collapsible:rotate-90" />
                                    Advanced Settings
                                </CardTitle>
                            </CardHeader>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <Form<RankingWeightsFormData>
                                {...CampaignRankingController.update.form.patch(
                                    campaign.id,
                                )}
                                options={{ preserveScroll: true }}
                                className="contents"
                            >
                                {({ errors, processing }) => {
                                    const fieldErrors = errors as Record<
                                        string,
                                        string | undefined
                                    >;

                                    return (
                                        <>
                                            <CardContent className="flex flex-col gap-6 py-(--card-spacing)">
                                                <section className="flex flex-col gap-3">
                                                    <div>
                                                        <h2 className="text-base font-medium">
                                                            Ranking weights
                                                        </h2>
                                                        <p className="text-sm text-muted-foreground">
                                                            Resume, essay, and
                                                            MCQ weights must
                                                            total 100.
                                                        </p>
                                                    </div>
                                                    <div className="grid gap-3 sm:grid-cols-3">
                                                        <div className="grid gap-2">
                                                            <Label htmlFor="ranking_resume_score">
                                                                Resume %
                                                            </Label>
                                                            <Input
                                                                id="ranking_resume_score"
                                                                name="ranking_weights[resume_score]"
                                                                type="number"
                                                                min={0}
                                                                max={100}
                                                                defaultValue={
                                                                    campaign
                                                                        .ranking_weights
                                                                        .resume_score
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
                                                            <Label htmlFor="ranking_essay_score">
                                                                Essay %
                                                            </Label>
                                                            <Input
                                                                id="ranking_essay_score"
                                                                name="ranking_weights[essay_score]"
                                                                type="number"
                                                                min={0}
                                                                max={100}
                                                                defaultValue={
                                                                    campaign
                                                                        .ranking_weights
                                                                        .essay_score
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
                                                            <Label htmlFor="ranking_mcq_score">
                                                                MCQ %
                                                            </Label>
                                                            <Input
                                                                id="ranking_mcq_score"
                                                                name="ranking_weights[mcq_score]"
                                                                type="number"
                                                                min={0}
                                                                max={100}
                                                                defaultValue={
                                                                    campaign
                                                                        .ranking_weights
                                                                        .mcq_score
                                                                }
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
                                                    <InputError
                                                        message={
                                                            fieldErrors.ranking_weights
                                                        }
                                                    />
                                                </section>
                                            </CardContent>
                                            <CardFooter className="-mb-(--card-spacing) justify-between gap-3 border-t bg-background py-(--card-spacing)">
                                                <p className="text-sm text-muted-foreground">
                                                    Apply these weights to
                                                    candidate ranking.
                                                </p>
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    {processing && <Spinner />}
                                                    Save weights
                                                </Button>
                                            </CardFooter>
                                        </>
                                    );
                                }}
                            </Form>
                        </CollapsibleContent>
                    </Card>
                </Collapsible>
                        </>
                    ) : null}
                </Deferred>
            </div>
        </>
    );
}

function GenerateAssessmentDialog({ campaignId }: { campaignId: number }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Sparkles data-icon="inline-start" />
                    Generate assessment
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Generate assessment</DialogTitle>
                    <DialogDescription>
                        Create draft questions for review.
                    </DialogDescription>
                </DialogHeader>

                <Form<GenerateAssessmentFormData>
                    {...CampaignAssessmentGenerationController.store.form(
                        campaignId,
                    )}
                    options={{
                        preserveScroll: true,
                    }}
                    onSuccess={() => setOpen(false)}
                    className="contents"
                >
                    {({ errors, processing }) => (
                        <>
                            <FieldGroup className="gap-4">
                                <div className="grid grid-cols-2 gap-3">
                                    <Field
                                        data-invalid={
                                            Boolean(errors.question_count) ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="generate-question-count">
                                            Questions
                                        </FieldLabel>
                                        <Input
                                            id="generate-question-count"
                                            name="question_count"
                                            type="number"
                                            min={1}
                                            max={20}
                                            defaultValue={6}
                                            required
                                            aria-invalid={
                                                Boolean(
                                                    errors.question_count,
                                                ) || undefined
                                            }
                                        />
                                        <FieldError
                                            errors={[
                                                {
                                                    message:
                                                        errors.question_count,
                                                },
                                            ]}
                                        />
                                    </Field>

                                    <Field
                                        data-invalid={
                                            Boolean(errors.difficulty) ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel>Difficulty</FieldLabel>
                                        <Select
                                            name="difficulty"
                                            defaultValue="mixed"
                                        >
                                            <SelectTrigger
                                                className="w-full"
                                                aria-invalid={
                                                    Boolean(
                                                        errors.difficulty,
                                                    ) || undefined
                                                }
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
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
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError
                                            errors={[
                                                {
                                                    message: errors.difficulty,
                                                },
                                            ]}
                                        />
                                    </Field>
                                </div>

                                <Field
                                    data-invalid={
                                        Boolean(errors.language) || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="generate-language">
                                        Language
                                    </FieldLabel>
                                    <Input
                                        id="generate-language"
                                        name="language"
                                        defaultValue="English"
                                        required
                                        aria-invalid={
                                            Boolean(errors.language) ||
                                            undefined
                                        }
                                    />
                                    <FieldError
                                        errors={[
                                            {
                                                message: errors.language,
                                            },
                                        ]}
                                    />
                                </Field>

                                <Field
                                    data-invalid={
                                        Boolean(errors.question_mix) ||
                                        undefined
                                    }
                                >
                                    <FieldLabel htmlFor="question_mix">
                                        Question mix
                                    </FieldLabel>
                                    <textarea
                                        id="question_mix"
                                        name="question_mix"
                                        rows={3}
                                        className={textareaClass}
                                        placeholder="3 multiple choice, 2 essay, 1 fill blank"
                                        aria-invalid={
                                            Boolean(errors.question_mix) ||
                                            undefined
                                        }
                                    />
                                    <FieldError
                                        errors={[
                                            {
                                                message: errors.question_mix,
                                            },
                                        ]}
                                    />
                                </Field>

                                <InputError message={errors.generation} />
                            </FieldGroup>

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    <Sparkles data-icon="inline-start" />
                                    Generate drafts
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function AddSectionSheet({ campaignId }: { campaignId: number }) {
    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button variant="outline" size="sm">
                    <Plus data-icon="inline-start" />
                    Add section
                </Button>
            </SheetTrigger>
            <SheetContent className="bg-card text-card-foreground data-[side=right]:sm:max-w-2xl">
                <SheetHeader>
                    <SheetTitle>Add section</SheetTitle>
                    <SheetDescription>
                        Create a new section for grouping campaign questions.
                    </SheetDescription>
                </SheetHeader>

                <Form<SectionFormData>
                    {...CampaignSectionController.store.form(campaignId)}
                    options={{
                        preserveScroll: true,
                    }}
                    className="flex flex-1 flex-col overflow-hidden"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="flex flex-1 flex-col gap-4 overflow-y-auto px-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="section-title">Title</Label>
                                    <Input
                                        id="section-title"
                                        name="title"
                                        required
                                    />
                                    <InputError message={errors.title} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="section-description">
                                        Description
                                    </Label>
                                    <textarea
                                        id="section-description"
                                        name="description"
                                        rows={4}
                                        className={textareaClass}
                                    />
                                    <InputError message={errors.description} />
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="duration_minutes">
                                            Minutes
                                        </Label>
                                        <Input
                                            id="duration_minutes"
                                            name="duration_minutes"
                                            type="number"
                                            min={1}
                                        />
                                        <InputError
                                            message={errors.duration_minutes}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="section-sort">
                                            Order
                                        </Label>
                                        <Input
                                            id="section-sort"
                                            name="sort_order"
                                            type="number"
                                            min={0}
                                            defaultValue={20}
                                            required
                                        />
                                        <InputError
                                            message={errors.sort_order}
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div className="grid gap-2">
                                        <Label>Scoring</Label>
                                        <Select
                                            name="scoring_mode"
                                            defaultValue="weighted"
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="weighted">
                                                    Weighted
                                                </SelectItem>
                                                <SelectItem value="points">
                                                    Points
                                                </SelectItem>
                                                <SelectItem value="percentage">
                                                    Percentage
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.scoring_mode}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="section-weight">
                                            Weight
                                        </Label>
                                        <Input
                                            id="section-weight"
                                            name="weight"
                                            type="number"
                                            min={1}
                                            defaultValue={100}
                                            required
                                        />
                                        <InputError message={errors.weight} />
                                    </div>
                                </div>
                            </div>

                            <SheetFooter className="flex-row items-center justify-between gap-3 border-t bg-background sm:flex-row">
                                <SheetClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                    >
                                        Cancel
                                    </Button>
                                </SheetClose>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    <Plus data-icon="inline-start" />
                                    Add section
                                </Button>
                            </SheetFooter>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}

function SectionActionsDropdown({
    campaignId,
    section,
    questionTypes,
    gradingModeOptions,
}: {
    campaignId: number;
    section: CampaignSection;
    questionTypes: QuestionTypeOption[];
    gradingModeOptions: GradingModeOption[];
}) {
    const [addQuestionOpen, setAddQuestionOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        size="icon-sm"
                        variant="outline"
                        aria-label="Open section actions"
                    >
                        <MoreHorizontal data-icon="inline-start" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-40">
                    <DropdownMenuItem
                        onSelect={(event) => {
                            event.preventDefault();
                            setAddQuestionOpen(true);
                        }}
                    >
                        Add question
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={(event) => {
                            event.preventDefault();
                            setDeleteOpen(true);
                        }}
                    >
                        Delete
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <AddQuestionSheet
                campaignId={campaignId}
                section={section}
                questionTypes={questionTypes}
                gradingModeOptions={gradingModeOptions}
                open={addQuestionOpen}
                onOpenChange={setAddQuestionOpen}
            />
            <DeleteSectionDialog
                campaignId={campaignId}
                section={section}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

function DeleteSectionDialog({
    campaignId,
    section,
    open,
    onOpenChange,
}: {
    campaignId: number;
    section: CampaignSection;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete section?</DialogTitle>
                    <DialogDescription>
                        This will permanently delete "{section.title}" and its
                        questions. This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...CampaignSectionController.destroy.form.delete([
                        campaignId,
                        section.id,
                    ])}
                    onSuccess={() => onOpenChange(false)}
                    className="flex flex-col gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <InputError message={errors.section} />

                            <DialogFooter>
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
                                    Delete section
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function AddQuestionSheet({
    campaignId,
    section,
    questionTypes,
    gradingModeOptions,
    open,
    onOpenChange,
}: {
    campaignId: number;
    section: CampaignSection;
    questionTypes: QuestionTypeOption[];
    gradingModeOptions: GradingModeOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="bg-card text-card-foreground data-[side=right]:sm:max-w-2xl">
                <SheetHeader>
                    <SheetTitle>Add question</SheetTitle>
                    <SheetDescription>
                        Add a question to {section.title}.
                    </SheetDescription>
                </SheetHeader>

                <Form<QuestionFormData>
                    {...CampaignQuestionController.store.form(campaignId)}
                    options={{
                        preserveScroll: true,
                    }}
                    className="flex flex-1 flex-col overflow-hidden"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="flex flex-1 flex-col gap-4 overflow-y-auto px-4">
                                <input
                                    type="hidden"
                                    name="campaign_section_id"
                                    value={section.id}
                                />
                                <InputError
                                    message={errors.campaign_section_id}
                                />

                                <div className="grid gap-3 md:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label>Type</Label>
                                        <Select
                                            name="type"
                                            defaultValue="long_text"
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
                                            defaultValue="ai"
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {gradingModeOptions.map(
                                                    (mode) => (
                                                        <SelectItem
                                                            key={mode.value}
                                                            value={mode.value}
                                                        >
                                                            {mode.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.grading_mode}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Difficulty</Label>
                                        <Select
                                            name="difficulty"
                                            defaultValue="medium"
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
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
                                    <Label htmlFor={`prompt-${section.id}`}>
                                        Prompt
                                    </Label>
                                    <textarea
                                        id={`prompt-${section.id}`}
                                        name="prompt"
                                        rows={4}
                                        required
                                        className={textareaClass}
                                    />
                                    <InputError message={errors.prompt} />
                                </div>

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`expected-rubric-${section.id}`}
                                    >
                                        Rubric
                                    </Label>
                                    <textarea
                                        id={`expected-rubric-${section.id}`}
                                        name="expected_rubric"
                                        rows={4}
                                        className={textareaClass}
                                    />
                                    <InputError
                                        message={errors.expected_rubric}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`options-${section.id}`}>
                                        Options
                                    </Label>
                                    <textarea
                                        id={`options-${section.id}`}
                                        name="options_text"
                                        rows={3}
                                        className={textareaClass}
                                    />
                                    <InputError message={errors.options_text} />
                                </div>

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`correct-answer-${section.id}`}
                                    >
                                        Correct answer
                                    </Label>
                                    <textarea
                                        id={`correct-answer-${section.id}`}
                                        name="correct_answer_text"
                                        rows={3}
                                        className={textareaClass}
                                        placeholder="One accepted answer per line. For matching pairs use left = right."
                                    />
                                    <InputError
                                        message={errors.correct_answer_text}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`skill-tags-${section.id}`}>
                                        Skill tags
                                    </Label>
                                    <textarea
                                        id={`skill-tags-${section.id}`}
                                        name="skill_tags_text"
                                        rows={3}
                                        className={textareaClass}
                                    />
                                    <InputError
                                        message={errors.skill_tags_text}
                                    />
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor={`points-${section.id}`}>
                                            Points
                                        </Label>
                                        <Input
                                            id={`points-${section.id}`}
                                            name="points"
                                            type="number"
                                            min={1}
                                            defaultValue={10}
                                            required
                                        />
                                        <InputError message={errors.points} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`question-sort-${section.id}`}
                                        >
                                            Order
                                        </Label>
                                        <Input
                                            id={`question-sort-${section.id}`}
                                            name="sort_order"
                                            type="number"
                                            min={0}
                                            defaultValue={10}
                                            required
                                        />
                                        <InputError
                                            message={errors.sort_order}
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center gap-3 rounded-md border border-border px-3 py-2.5">
                                    <input
                                        type="hidden"
                                        name="ai_generated"
                                        value="0"
                                    />
                                    <input
                                        type="hidden"
                                        name="is_required"
                                        value="0"
                                    />
                                    <Checkbox
                                        id={`required-${section.id}`}
                                        name="is_required"
                                        value="1"
                                        defaultChecked
                                    />
                                    <Label
                                        htmlFor={`required-${section.id}`}
                                        className="text-sm font-normal"
                                    >
                                        Required
                                    </Label>
                                </div>
                            </div>

                            <SheetFooter className="flex-row items-center justify-between gap-3 border-t bg-background sm:flex-row">
                                <SheetClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                    >
                                        Cancel
                                    </Button>
                                </SheetClose>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    <Plus data-icon="inline-start" />
                                    Add question
                                </Button>
                            </SheetFooter>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}

function CampaignOverviewCard({
    campaign,
    invitations,
    latestInviteUrl,
    deleteDialogOpen,
    onDeleteDialogOpenChange,
}: {
    campaign: Campaign;
    invitations: CampaignInvitationRow[];
    latestInviteUrl: string | undefined;
    deleteDialogOpen: boolean;
    onDeleteDialogOpenChange: (open: boolean) => void;
}) {
    return (
        <Collapsible className="group/collapsible">
            <Card className="gap-0">
                <CardHeader>
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <CollapsibleTrigger asChild>
                            <button
                                type="button"
                                className="flex cursor-pointer items-center gap-2 text-left"
                            >
                                <ChevronRight className="size-4 shrink-0 text-muted-foreground transition-transform group-data-[state=open]/collapsible:rotate-90" />
                                <CardTitle>{campaign.title}</CardTitle>
                            </button>
                        </CollapsibleTrigger>

                        <div className="flex flex-wrap items-center gap-2 md:justify-end">
                            <CandidateInvitationsDialog
                                campaign={campaign}
                                invitations={invitations}
                                latestInviteUrl={latestInviteUrl}
                            />
                            <CampaignStatusButton campaign={campaign} />
                            <CampaignActionsDropdown
                                campaign={campaign}
                                onDelete={() => onDeleteDialogOpenChange(true)}
                            />
                        </div>
                    </div>
                </CardHeader>

                <CollapsibleContent>
                    <CardContent className="py-(--card-spacing)">
                        <div className="divide-y">
                            <CampaignOverviewField
                                label="Role title"
                                value={campaign.role_title}
                            />
                            <CampaignOverviewField
                                label="Seniority"
                                value={
                                    campaign.seniority ?? 'Not specified'
                                }
                            />
                            <CampaignOverviewField
                                label="Assessment language"
                                value={campaign.language}
                            />
                            <CampaignOverviewField
                                label="Threshold score"
                                value={`${campaign.threshold_score}`}
                            />

                            <div className="flex flex-col gap-2 py-4 first:pt-0 last:pb-0 sm:flex-row sm:gap-6">
                                <p className="text-sm font-medium sm:w-48 sm:shrink-0">
                                    Required skills
                                </p>
                                <div className="flex-1">
                                    {campaign.required_skills.length > 0 ? (
                                        <div className="flex flex-wrap gap-2">
                                            {campaign.required_skills.map(
                                                (skill) => (
                                                    <Badge
                                                        key={skill}
                                                        variant="outline"
                                                    >
                                                        {skill}
                                                    </Badge>
                                                ),
                                            )}
                                        </div>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            Not specified
                                        </p>
                                    )}
                                </div>
                            </div>

                            <CampaignOverviewField
                                label="Job description"
                                value={
                                    campaign.job_description ??
                                    'Not specified'
                                }
                            />
                        </div>
                    </CardContent>
                </CollapsibleContent>

                <DeleteCampaignDialog
                    campaign={campaign}
                    open={deleteDialogOpen}
                    onOpenChange={onDeleteDialogOpenChange}
                />
            </Card>
        </Collapsible>
    );
}

function CampaignOverviewField({
    label,
    value,
}: {
    label: string;
    value: string;
}) {
    return (
        <div className="flex flex-col gap-1 py-4 first:pt-0 last:pb-0 sm:flex-row sm:gap-6">
            <p className="text-sm font-medium sm:w-48 sm:shrink-0">{label}</p>
            <p className="flex-1 text-sm whitespace-pre-line text-muted-foreground">
                {value}
            </p>
        </div>
    );
}

function CandidateInvitationsDialog({
    campaign,
    invitations,
    latestInviteUrl,
}: {
    campaign: Campaign;
    invitations: CampaignInvitationRow[];
    latestInviteUrl: string | undefined;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Share2 data-icon="inline-start" />
                    Share
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Candidate invitations</DialogTitle>
                    <DialogDescription>
                        Assign candidates to this campaign and share their
                        invite links before they can open the exam.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    action={admin.campaigns.invitations.store.url(campaign.id)}
                    method="post"
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing }) => (
                        <FieldGroup>
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start">
                                <Field
                                    className="flex-1"
                                    data-invalid={
                                        Boolean(errors.email) || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="invitation-email">
                                        Candidate email
                                    </FieldLabel>
                                    <Input
                                        id="invitation-email"
                                        name="email"
                                        type="email"
                                        placeholder="candidate@example.com"
                                        required
                                        aria-invalid={
                                            Boolean(errors.email) || undefined
                                        }
                                    />
                                    <FieldError
                                        errors={[
                                            {
                                                message: errors.email,
                                            },
                                        ]}
                                    />
                                </Field>
                                <Button
                                    disabled={processing}
                                    type="submit"
                                    className="sm:mt-6"
                                >
                                    {processing && <Spinner />}
                                    <Mail data-icon="inline-start" />
                                    Send invitation
                                </Button>
                            </div>
                            <input type="hidden" name="send_email" value="1" />
                        </FieldGroup>
                    )}
                </Form>

                {latestInviteUrl ? (
                    <p className="rounded-md bg-background px-3 py-2 text-sm break-all">
                        Latest invite link: {latestInviteUrl}
                    </p>
                ) : null}

                <div className="overflow-x-auto">
                    <Table className="min-w-[640px]">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Email</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Sent</TableHead>
                                <TableHead>Accepted</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {invitations.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        className="text-muted-foreground"
                                        colSpan={4}
                                    >
                                        No invitations yet.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                invitations.map((invitation) => (
                                    <TableRow key={invitation.id}>
                                        <TableCell>
                                            {invitation.email}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary">
                                                {invitation.status_label}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {invitation.sent_at
                                                ? new Date(
                                                      invitation.sent_at,
                                                  ).toLocaleString()
                                                : '—'}
                                        </TableCell>
                                        <TableCell>
                                            {invitation.accepted_at
                                                ? new Date(
                                                      invitation.accepted_at,
                                                  ).toLocaleString()
                                                : '—'}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function CampaignStatusButton({ campaign }: { campaign: Campaign }) {
    if (campaign.status === 'archived') {
        return (
            <Form
                {...CampaignStatusController.draft.form(campaign.id)}
                options={{ preserveScroll: true }}
            >
                {({ processing }) => (
                    <Button size="sm" variant="outline" disabled={processing}>
                        {processing && <Spinner />}
                        Move to draft
                    </Button>
                )}
            </Form>
        );
    }

    if (campaign.status === 'active') {
        return (
            <Form
                {...CampaignStatusController.archive.form(campaign.id)}
                options={{ preserveScroll: true }}
            >
                {({ processing }) => (
                    <Button size="sm" variant="outline" disabled={processing}>
                        {processing && <Spinner />}
                        Archive
                    </Button>
                )}
            </Form>
        );
    }

    return (
        <Form
            {...CampaignController.publish.form(campaign.id)}
            options={{ preserveScroll: true }}
        >
            {({ errors, processing }) => (
                <div className="flex flex-col gap-1">
                    <Button
                        size="sm"
                        disabled={processing || !campaign.can_publish}
                    >
                        {processing && <Spinner />}
                        <Rocket data-icon="inline-start" />
                        Publish
                    </Button>
                    <InputError message={errors.campaign} />
                </div>
            )}
        </Form>
    );
}

function CampaignActionsDropdown({
    campaign,
    onDelete,
}: {
    campaign: Campaign;
    onDelete: () => void;
}) {
    const [editOpen, setEditOpen] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button size="icon-sm" variant="outline">
                        <MoreHorizontal data-icon="inline-start" />
                        <span className="sr-only">Open campaign actions</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" className="w-44">
                    <DropdownMenuGroup>
                        <DropdownMenuItem
                            onSelect={(event) => {
                                event.preventDefault();
                                setEditOpen(true);
                            }}
                        >
                            Edit campaign
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={(event) => {
                            event.preventDefault();
                            onDelete();
                        }}
                    >
                        Delete campaign
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <Sheet open={editOpen} onOpenChange={setEditOpen}>
                <SheetContent className="bg-card text-card-foreground data-[side=right]:sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>Edit campaign</SheetTitle>
                        <SheetDescription>
                            Update role context, required skills, and scoring
                            threshold.
                        </SheetDescription>
                    </SheetHeader>
                    <CampaignForm
                        action={CampaignController.update.form.patch(
                            campaign.id,
                        )}
                        submitLabel="Save changes"
                        campaign={campaign}
                        onSuccess={() => setEditOpen(false)}
                        onCancel={() => setEditOpen(false)}
                        className="flex-1 overflow-hidden"
                        bodyClassName="flex-1 overflow-y-auto px-4"
                        footerClassName="mt-auto border-t bg-background p-4"
                    />
                </SheetContent>
            </Sheet>
        </>
    );
}

function DeleteCampaignDialog({
    campaign,
    open,
    onOpenChange,
}: {
    campaign: Campaign;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete campaign?</DialogTitle>
                    <DialogDescription>
                        This will permanently delete "{campaign.title}". This
                        action cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...CampaignController.destroy.form.delete(campaign.id)}
                    onSuccess={() => onOpenChange(false)}
                    className="flex flex-col gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <InputError message={errors.campaign} />

                            <DialogFooter>
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
                                    Delete campaign
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function QuestionActionsDropdown({
    campaignId,
    question,
    sections,
    questionTypes,
    gradingModeOptions,
}: {
    campaignId: number;
    question: CampaignQuestion;
    sections: CampaignSection[];
    questionTypes: QuestionTypeOption[];
    gradingModeOptions: GradingModeOption[];
}) {
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const canRegenerateOptions =
        question.status === 'draft' && question.type === 'multiple_choice';
    const canConvertToMcq =
        question.status === 'draft' &&
        (question.type === 'short_text' || question.type === 'long_text');
    const canApprove = question.status === 'draft';

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        size="icon-sm"
                        variant="outline"
                        aria-label="Open question actions"
                    >
                        <MoreHorizontal data-icon="inline-start" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-40">
                    {canRegenerateOptions || canConvertToMcq || canApprove ? (
                        <DropdownMenuGroup>
                            {canRegenerateOptions ? (
                                <DropdownMenuItem asChild>
                                    <Form
                                        {...CampaignQuestionController.regenerateMcqOptions.form(
                                            [campaignId, question.id],
                                        )}
                                        options={{
                                            preserveScroll: true,
                                        }}
                                    >
                                        {({ processing }) => (
                                            <button
                                                type="submit"
                                                disabled={processing}
                                                className="w-full text-left"
                                            >
                                                Regenerate options
                                            </button>
                                        )}
                                    </Form>
                                </DropdownMenuItem>
                            ) : null}
                            {canConvertToMcq ? (
                                <DropdownMenuItem asChild>
                                    <Form
                                        {...CampaignQuestionController.convertToMcq.form(
                                            [campaignId, question.id],
                                        )}
                                        options={{
                                            preserveScroll: true,
                                        }}
                                    >
                                        {({ processing }) => (
                                            <button
                                                type="submit"
                                                disabled={processing}
                                                className="w-full text-left"
                                            >
                                                Convert to MCQ
                                            </button>
                                        )}
                                    </Form>
                                </DropdownMenuItem>
                            ) : null}
                            {canApprove ? (
                                <DropdownMenuItem asChild>
                                    <Form
                                        {...CampaignQuestionController.approve.form(
                                            [campaignId, question.id],
                                        )}
                                        options={{
                                            preserveScroll: true,
                                        }}
                                    >
                                        {({ processing }) => (
                                            <button
                                                type="submit"
                                                disabled={processing}
                                                className="w-full text-left"
                                            >
                                                Approve
                                            </button>
                                        )}
                                    </Form>
                                </DropdownMenuItem>
                            ) : null}
                        </DropdownMenuGroup>
                    ) : null}
                    {canRegenerateOptions || canConvertToMcq || canApprove ? (
                        <DropdownMenuSeparator />
                    ) : null}
                    <DropdownMenuGroup>
                        <DropdownMenuItem
                            onSelect={(event) => {
                                event.preventDefault();
                                setEditOpen(true);
                            }}
                        >
                            Edit snapshot
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={(event) => {
                                event.preventDefault();
                                setDeleteOpen(true);
                            }}
                        >
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>

            <EditCampaignQuestionSheet
                campaignId={campaignId}
                question={question}
                sections={sections}
                questionTypes={questionTypes}
                gradingModeOptions={gradingModeOptions}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteQuestionDialog
                campaignId={campaignId}
                question={question}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

function DeleteQuestionDialog({
    campaignId,
    question,
    open,
    onOpenChange,
}: {
    campaignId: number;
    question: CampaignQuestion;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete question?</DialogTitle>
                    <DialogDescription>
                        This will permanently delete this question snapshot.
                        This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...CampaignQuestionController.destroy.form.delete([
                        campaignId,
                        question.id,
                    ])}
                    onSuccess={() => onOpenChange(false)}
                    className="flex flex-col gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <InputError message={errors.question} />

                            <DialogFooter>
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
                                    Delete question
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function EditCampaignQuestionSheet({
    campaignId,
    question,
    sections,
    questionTypes,
    gradingModeOptions,
    open,
    onOpenChange,
}: {
    campaignId: number;
    question: CampaignQuestion;
    sections: CampaignSection[];
    questionTypes: QuestionTypeOption[];
    gradingModeOptions: GradingModeOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="bg-card text-card-foreground data-[side=right]:sm:max-w-2xl">
                <SheetHeader>
                    <SheetTitle>Edit question snapshot</SheetTitle>
                    <SheetDescription>
                        Update this campaign question without changing other
                        questions.
                    </SheetDescription>
                </SheetHeader>

                <Form<QuestionFormData>
                    {...CampaignQuestionController.update.form.patch([
                        campaignId,
                        question.id,
                    ])}
                    options={{
                        preserveScroll: true,
                    }}
                    className="flex flex-1 flex-col overflow-hidden"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="flex flex-1 flex-col gap-4 overflow-y-auto px-4">
                                <div className="grid gap-3 md:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label>Section</Label>
                                        <Select
                                            name="campaign_section_id"
                                            defaultValue={question.campaign_section_id.toString()}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {sections.map((section) => (
                                                    <SelectItem
                                                        key={section.id}
                                                        value={section.id.toString()}
                                                    >
                                                        {section.title}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.campaign_section_id}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Type</Label>
                                        <Select
                                            name="type"
                                            defaultValue={question.type}
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
                                            defaultValue={question.grading_mode}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {gradingModeOptions.map(
                                                    (mode) => (
                                                        <SelectItem
                                                            key={mode.value}
                                                            value={mode.value}
                                                        >
                                                            {mode.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.grading_mode}
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`edit-prompt-${question.id}`}
                                    >
                                        Prompt
                                    </Label>
                                    <textarea
                                        id={`edit-prompt-${question.id}`}
                                        name="prompt"
                                        rows={4}
                                        defaultValue={question.prompt}
                                        required
                                        className={textareaClass}
                                    />
                                    <InputError message={errors.prompt} />
                                </div>

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`edit-rubric-${question.id}`}
                                    >
                                        Rubric
                                    </Label>
                                    <textarea
                                        id={`edit-rubric-${question.id}`}
                                        name="expected_rubric"
                                        rows={4}
                                        defaultValue={
                                            question.expected_rubric ?? ''
                                        }
                                        className={textareaClass}
                                    />
                                    <InputError
                                        message={errors.expected_rubric}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`edit-options-${question.id}`}
                                    >
                                        Options
                                    </Label>
                                    <textarea
                                        id={`edit-options-${question.id}`}
                                        name="options_text"
                                        rows={3}
                                        defaultValue={question.options.join(
                                            '\n',
                                        )}
                                        className={textareaClass}
                                    />
                                    <InputError message={errors.options_text} />
                                </div>

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`edit-answer-${question.id}`}
                                    >
                                        Correct answer
                                    </Label>
                                    <textarea
                                        id={`edit-answer-${question.id}`}
                                        name="correct_answer_text"
                                        rows={3}
                                        defaultValue={question.correct_answer.join(
                                            '\n',
                                        )}
                                        className={textareaClass}
                                        placeholder="One accepted answer per line. For matching pairs use left = right."
                                    />
                                    <InputError
                                        message={errors.correct_answer_text}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`edit-tags-${question.id}`}>
                                        Skill tags
                                    </Label>
                                    <textarea
                                        id={`edit-tags-${question.id}`}
                                        name="skill_tags_text"
                                        rows={3}
                                        defaultValue={question.skill_tags.join(
                                            '\n',
                                        )}
                                        className={textareaClass}
                                    />
                                    <InputError
                                        message={errors.skill_tags_text}
                                    />
                                </div>

                                <div className="grid gap-3 md:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`edit-points-${question.id}`}
                                        >
                                            Points
                                        </Label>
                                        <Input
                                            id={`edit-points-${question.id}`}
                                            name="points"
                                            type="number"
                                            min={1}
                                            defaultValue={question.points}
                                            required
                                        />
                                        <InputError message={errors.points} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Difficulty</Label>
                                        <Select
                                            name="difficulty"
                                            defaultValue={question.difficulty}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
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

                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`edit-sort-${question.id}`}
                                        >
                                            Order
                                        </Label>
                                        <Input
                                            id={`edit-sort-${question.id}`}
                                            name="sort_order"
                                            type="number"
                                            min={0}
                                            defaultValue={question.sort_order}
                                            required
                                        />
                                        <InputError
                                            message={errors.sort_order}
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center gap-3 rounded-md border border-border px-3 py-2.5">
                                    <input
                                        type="hidden"
                                        name="ai_generated"
                                        value={
                                            question.ai_generated ? '1' : '0'
                                        }
                                    />
                                    <input
                                        type="hidden"
                                        name="is_required"
                                        value="0"
                                    />
                                    <Checkbox
                                        id={`edit-required-${question.id}`}
                                        name="is_required"
                                        value="1"
                                        defaultChecked={question.is_required}
                                    />
                                    <Label
                                        htmlFor={`edit-required-${question.id}`}
                                        className="text-sm font-normal"
                                    >
                                        Required
                                    </Label>
                                </div>
                            </div>

                            <SheetFooter className="flex-row items-center justify-between gap-3 border-t bg-background sm:flex-row">
                                <SheetClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                    >
                                        Cancel
                                    </Button>
                                </SheetClose>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Save question
                                </Button>
                            </SheetFooter>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}

AdminCampaignsShow.layout = {
    breadcrumbs: [
        {
            title: 'Campaigns',
            href: admin.campaigns.index(),
        },
        {
            title: 'Detail',
            href: admin.campaigns.show(0),
        },
    ],
};
