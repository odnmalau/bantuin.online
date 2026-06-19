import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    Check,
    Mail,
    Plus,
    Rocket,
    Sparkles,
    Trash2,
    ArrowRightLeft,
} from 'lucide-react';
import { useState } from 'react';
import CampaignAssessmentGenerationController from '@/actions/App/Http/Controllers/Admin/CampaignAssessmentGenerationController';
import CampaignController from '@/actions/App/Http/Controllers/Admin/CampaignController';
import CampaignQuestionController from '@/actions/App/Http/Controllers/Admin/CampaignQuestionController';
import CampaignQuestionImportController from '@/actions/App/Http/Controllers/Admin/CampaignQuestionImportController';
import CampaignSectionController from '@/actions/App/Http/Controllers/Admin/CampaignSectionController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
    source_bank_question_id: number | null;
    source_question_bank: string | null;
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
    nice_to_have_skills: string[];
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
    ai_generation_notes: string | null;
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

type LibraryQuestion = {
    id: number;
    type: string;
    type_label: string;
    grading_mode: string;
    grading_mode_label: string;
    prompt: string;
    points: number;
    difficulty: string;
    skill_tags: string[];
};

type QuestionBankImport = {
    id: number;
    title: string;
    skill_area: string | null;
    difficulty: string;
    questions: LibraryQuestion[];
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
    campaign: Campaign;
    invitations: CampaignInvitationRow[];
    questionTypes: QuestionTypeOption[];
    gradingModeOptions: GradingModeOption[];
    questionBanks: QuestionBankImport[];
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

const textareaClass =
    'flex min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50';

export default function AdminCampaignsShow({
    campaign,
    invitations,
    questionTypes,
    gradingModeOptions,
    questionBanks,
}: Props) {
    const page = usePage<{ flash?: { campaign_invite_url?: string } }>();
    const latestInviteUrl = page.props.flash?.campaign_invite_url;
    const firstSectionId = campaign.sections[0]?.id.toString();
    const importableQuestionBanks = questionBanks.filter(
        (questionBank) => questionBank.questions.length > 0,
    );
    const firstImportableBankId = importableQuestionBanks[0]?.id.toString();
    const [selectedBankId, setSelectedBankId] = useState(
        firstImportableBankId ?? '',
    );
    const selectedBank =
        importableQuestionBanks.find(
            (questionBank) => questionBank.id.toString() === selectedBankId,
        ) ?? importableQuestionBanks[0];
    const [selectedQuestionId, setSelectedQuestionId] = useState(
        selectedBank?.questions[0]?.id.toString() ?? '',
    );
    const selectedQuestion = selectedBank?.questions.find(
        (question) => question.id.toString() === selectedQuestionId,
    );

    return (
        <>
            <Head title={campaign.title} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={campaign.title}
                        description={`${campaign.role_title}${campaign.seniority ? `, ${campaign.seniority}` : ''}`}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Form
                            {...CampaignController.publish.form(campaign.id)}
                            options={{
                                preserveScroll: true,
                            }}
                        >
                            {({ errors, processing }) => (
                                <div className="grid gap-1">
                                    <Button
                                        disabled={
                                            processing || !campaign.can_publish
                                        }
                                    >
                                        {processing && <Spinner />}
                                        <Rocket />
                                        Publish campaign
                                    </Button>
                                    <InputError message={errors.campaign} />
                                </div>
                            )}
                        </Form>
                        <Button variant="outline" asChild>
                            <Link href={admin.campaigns.edit(campaign.id)}>
                                Edit campaign
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={admin.campaigns.index()}>
                                Back to campaigns
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">Status</p>
                        <Badge
                            className="mt-2"
                            variant={
                                campaign.status === 'active'
                                    ? 'default'
                                    : 'secondary'
                            }
                        >
                            {campaign.status_label}
                        </Badge>
                    </div>
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            Threshold
                        </p>
                        <p className="mt-2 text-2xl font-semibold">
                            {campaign.threshold_score}
                        </p>
                    </div>
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            Sections
                        </p>
                        <p className="mt-2 text-2xl font-semibold">
                            {campaign.sections.length}
                        </p>
                    </div>
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            Questions
                        </p>
                        <p className="mt-2 text-2xl font-semibold">
                            {campaign.sections.reduce(
                                (total, section) =>
                                    total + section.questions.length,
                                0,
                            )}
                        </p>
                    </div>
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            Approved
                        </p>
                        <p className="mt-2 text-2xl font-semibold">
                            {campaign.approved_questions_count}
                        </p>
                    </div>
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            Ranking weights
                        </p>
                        <p className="mt-2 text-sm font-medium">
                            R {campaign.ranking_weights.resume_score}% · E{' '}
                            {campaign.ranking_weights.essay_score}% · M{' '}
                            {campaign.ranking_weights.mcq_score}%
                        </p>
                        {!campaign.ranking_weights_configured ? (
                            <p className="mt-1 text-xs text-muted-foreground">
                                Using application defaults until custom weights
                                are saved.
                            </p>
                        ) : null}
                    </div>
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">Drafts</p>
                        <p className="mt-2 text-2xl font-semibold">
                            {campaign.draft_questions_count}
                        </p>
                    </div>
                </div>

                <section className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="space-y-1">
                            <h2 className="text-base font-medium">
                                Candidate invitations
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Assign candidates to this campaign and share
                                their invite links before they can open the
                                exam.
                            </p>
                        </div>
                    </div>

                    <Form
                        action={admin.campaigns.invitations.store.url(
                            campaign.id,
                        )}
                        method="post"
                        options={{ preserveScroll: true }}
                        className="mt-4 flex flex-wrap items-end gap-3"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid min-w-64 flex-1 gap-2">
                                    <Label htmlFor="invitation-email">
                                        Candidate email
                                    </Label>
                                    <Input
                                        id="invitation-email"
                                        name="email"
                                        type="email"
                                        placeholder="candidate@example.com"
                                        required
                                    />
                                    <InputError message={errors.email} />
                                </div>
                                <input
                                    type="hidden"
                                    name="send_email"
                                    value="1"
                                />
                                <Button disabled={processing} type="submit">
                                    {processing && <Spinner />}
                                    <Mail />
                                    Send invitation
                                </Button>
                            </>
                        )}
                    </Form>

                    {latestInviteUrl ? (
                        <p className="mt-4 rounded-md bg-muted px-3 py-2 text-sm break-all">
                            Latest invite link: {latestInviteUrl}
                        </p>
                    ) : null}

                    <div className="mt-6 overflow-x-auto">
                        <table className="w-full min-w-[640px] text-left text-sm">
                            <thead className="text-muted-foreground">
                                <tr>
                                    <th className="pb-2 font-medium">Email</th>
                                    <th className="pb-2 font-medium">Status</th>
                                    <th className="pb-2 font-medium">Sent</th>
                                    <th className="pb-2 font-medium">
                                        Accepted
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {invitations.length === 0 ? (
                                    <tr>
                                        <td
                                            className="py-3 text-muted-foreground"
                                            colSpan={4}
                                        >
                                            No invitations yet.
                                        </td>
                                    </tr>
                                ) : (
                                    invitations.map((invitation) => (
                                        <tr
                                            key={invitation.id}
                                            className="border-t border-sidebar-border/70"
                                        >
                                            <td className="py-3">
                                                {invitation.email}
                                            </td>
                                            <td className="py-3">
                                                <Badge variant="secondary">
                                                    {invitation.status_label}
                                                </Badge>
                                            </td>
                                            <td className="py-3">
                                                {invitation.sent_at ?? '—'}
                                            </td>
                                            <td className="py-3">
                                                {invitation.accepted_at ?? '—'}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                    <div className="grid gap-4 lg:grid-cols-[1fr_320px]">
                        <div className="space-y-4">
                            <div>
                                <h2 className="text-base font-medium">
                                    Role context
                                </h2>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Language: {campaign.language}
                                </p>
                                <p className="mt-2 text-sm whitespace-pre-wrap text-muted-foreground">
                                    {campaign.job_description || '-'}
                                </p>
                            </div>

                            <div>
                                <h2 className="text-base font-medium">
                                    AI notes
                                </h2>
                                <p className="mt-2 text-sm whitespace-pre-wrap text-muted-foreground">
                                    {campaign.ai_generation_notes || '-'}
                                </p>
                            </div>

                            {campaign.ai_generation_audit.length > 0 ? (
                                <div>
                                    <h2 className="text-base font-medium">
                                        Generation audit
                                    </h2>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Metadata from each Qwen assessment
                                        generation run (newest last).
                                    </p>
                                    <ul className="mt-3 space-y-3">
                                        {campaign.ai_generation_audit.map(
                                            (entry, index) => (
                                                <li
                                                    key={`${entry.generated_at}-${index}`}
                                                    className="rounded-md border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border"
                                                >
                                                    <p className="font-medium">
                                                        Run {index + 1}
                                                    </p>
                                                    <dl className="mt-2 grid gap-1 text-xs text-muted-foreground sm:grid-cols-2">
                                                        <div>
                                                            <dt className="inline">
                                                                Generated:{' '}
                                                            </dt>
                                                            <dd className="inline">
                                                                {
                                                                    entry.generated_at
                                                                }
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt className="inline">
                                                                Model:{' '}
                                                            </dt>
                                                            <dd className="inline">
                                                                {entry.model} (
                                                                {entry.provider}
                                                                )
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt className="inline">
                                                                Prompt
                                                                version:{' '}
                                                            </dt>
                                                            <dd className="inline">
                                                                {
                                                                    entry.prompt_version
                                                                }
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt className="inline">
                                                                Created:{' '}
                                                            </dt>
                                                            <dd className="inline">
                                                                {
                                                                    entry.questions_created
                                                                }{' '}
                                                                questions in{' '}
                                                                {
                                                                    entry.sections_created
                                                                }{' '}
                                                                sections
                                                            </dd>
                                                        </div>
                                                    </dl>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </div>
                            ) : null}
                        </div>

                        <div className="space-y-4">
                            <div>
                                <h2 className="text-base font-medium">
                                    Required skills
                                </h2>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {campaign.required_skills.length > 0 ? (
                                        campaign.required_skills.map(
                                            (skill) => (
                                                <Badge
                                                    key={skill}
                                                    variant="outline"
                                                >
                                                    {skill}
                                                </Badge>
                                            ),
                                        )
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            -
                                        </p>
                                    )}
                                </div>
                            </div>
                            <div>
                                <h2 className="text-base font-medium">
                                    Nice-to-have skills
                                </h2>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {campaign.nice_to_have_skills.length > 0 ? (
                                        campaign.nice_to_have_skills.map(
                                            (skill) => (
                                                <Badge
                                                    key={skill}
                                                    variant="secondary"
                                                >
                                                    {skill}
                                                </Badge>
                                            ),
                                        )
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            -
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div className="grid gap-6 xl:grid-cols-[1fr_380px]">
                    <section className="space-y-4">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <h2 className="text-base font-medium">
                                Sections and questions
                            </h2>
                            {campaign.draft_questions_count > 0 ? (
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
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            <Check />
                                            Approve all drafts
                                        </Button>
                                    )}
                                </Form>
                            ) : null}
                        </div>

                        {campaign.sections.length === 0 ? (
                            <div className="rounded-lg border border-sidebar-border/70 p-6 text-sm text-muted-foreground dark:border-sidebar-border">
                                No sections yet.
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {campaign.sections.map((section) => (
                                    <div
                                        key={section.id}
                                        className="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-3 border-b p-4">
                                            <div className="space-y-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h3 className="font-medium">
                                                        {section.title}
                                                    </h3>
                                                    <Badge variant="outline">
                                                        {section.weight} weight
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
                                                        {section.description}
                                                    </p>
                                                ) : null}
                                            </div>
                                            <Form
                                                {...CampaignSectionController.destroy.form.delete(
                                                    [campaign.id, section.id],
                                                )}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        size="icon"
                                                        variant="outline"
                                                        disabled={processing}
                                                        aria-label="Delete section"
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                )}
                                            </Form>
                                        </div>

                                        {section.questions.length === 0 ? (
                                            <div className="p-4 text-sm text-muted-foreground">
                                                No questions in this section.
                                            </div>
                                        ) : (
                                            <div className="divide-y">
                                                {section.questions.map(
                                                    (question) => (
                                                        <div
                                                            key={question.id}
                                                            className="grid gap-4 p-4 lg:grid-cols-[1fr_160px]"
                                                        >
                                                            <div className="space-y-2">
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
                                                                {question.source_question_bank ? (
                                                                    <p className="text-xs text-muted-foreground">
                                                                        Imported
                                                                        from{' '}
                                                                        {
                                                                            question.source_question_bank
                                                                        }
                                                                    </p>
                                                                ) : null}
                                                                <EditCampaignQuestionForm
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
                                                            <div className="flex flex-col items-end justify-start gap-2">
                                                                {question.status ===
                                                                    'draft' &&
                                                                question.type ===
                                                                    'multiple_choice' ? (
                                                                    <Form
                                                                        {...CampaignQuestionController.regenerateMcqOptions.form(
                                                                            [
                                                                                campaign.id,
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
                                                                {question.status ===
                                                                    'draft' &&
                                                                (question.type ===
                                                                    'short_text' ||
                                                                    question.type ===
                                                                        'long_text') ? (
                                                                    <Form
                                                                        {...CampaignQuestionController.convertToMcq.form(
                                                                            [
                                                                                campaign.id,
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
                                                                                    variant="outline"
                                                                                    disabled={
                                                                                        processing
                                                                                    }
                                                                                >
                                                                                    {processing && (
                                                                                        <Spinner />
                                                                                    )}
                                                                                    <ArrowRightLeft />
                                                                                    Convert
                                                                                    to
                                                                                    MCQ
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
                                                                {question.status ===
                                                                'draft' ? (
                                                                    <Form
                                                                        {...CampaignQuestionController.approve.form(
                                                                            [
                                                                                campaign.id,
                                                                                question.id,
                                                                            ],
                                                                        )}
                                                                        options={{
                                                                            preserveScroll: true,
                                                                        }}
                                                                    >
                                                                        {({
                                                                            processing,
                                                                        }) => (
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
                                                                                <Check />
                                                                                Approve
                                                                            </Button>
                                                                        )}
                                                                    </Form>
                                                                ) : null}
                                                                <Form
                                                                    {...CampaignQuestionController.destroy.form.delete(
                                                                        [
                                                                            campaign.id,
                                                                            question.id,
                                                                        ],
                                                                    )}
                                                                >
                                                                    {({
                                                                        processing,
                                                                    }) => (
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
                                                    ),
                                                )}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>

                    <aside className="space-y-6">
                        <section className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <h2 className="text-base font-medium">
                                Generate assessment
                            </h2>
                            <Form<GenerateAssessmentFormData>
                                {...CampaignAssessmentGenerationController.store.form(
                                    campaign.id,
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
                                                    defaultValue={6}
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
                                                    defaultValue="mixed"
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
                                                placeholder="3 multiple choice, 2 essay, 1 fill blank"
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
                                        >
                                            {processing && <Spinner />}
                                            <Sparkles />
                                            Generate drafts
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </section>

                        <section className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <h2 className="text-base font-medium">
                                Add section
                            </h2>
                            <Form<SectionFormData>
                                {...CampaignSectionController.store.form(
                                    campaign.id,
                                )}
                                options={{
                                    preserveScroll: true,
                                }}
                                className="mt-4 space-y-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="section-title">
                                                Title
                                            </Label>
                                            <Input
                                                id="section-title"
                                                name="title"
                                                required
                                            />
                                            <InputError
                                                message={errors.title}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="section-description">
                                                Description
                                            </Label>
                                            <textarea
                                                id="section-description"
                                                name="description"
                                                rows={3}
                                                className={textareaClass}
                                            />
                                            <InputError
                                                message={errors.description}
                                            />
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
                                                    message={
                                                        errors.duration_minutes
                                                    }
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
                                                    message={
                                                        errors.scoring_mode
                                                    }
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
                                                <InputError
                                                    message={errors.weight}
                                                />
                                            </div>
                                        </div>

                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            <Plus />
                                            Add section
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </section>

                        <section className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <h2 className="text-base font-medium">
                                Import from library
                            </h2>
                            <Form
                                {...CampaignQuestionImportController.store.form(
                                    campaign.id,
                                )}
                                options={{
                                    preserveScroll: true,
                                }}
                                className="mt-4 space-y-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label>Library</Label>
                                            <Select
                                                value={selectedBankId}
                                                onValueChange={(value) => {
                                                    setSelectedBankId(value);

                                                    const nextBank =
                                                        importableQuestionBanks.find(
                                                            (questionBank) =>
                                                                questionBank.id.toString() ===
                                                                value,
                                                        );

                                                    setSelectedQuestionId(
                                                        nextBank?.questions[0]?.id.toString() ??
                                                            '',
                                                    );
                                                }}
                                                disabled={
                                                    importableQuestionBanks.length ===
                                                    0
                                                }
                                            >
                                                <SelectTrigger className="w-full">
                                                    <SelectValue placeholder="Choose library" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {importableQuestionBanks.map(
                                                        (questionBank) => (
                                                            <SelectItem
                                                                key={
                                                                    questionBank.id
                                                                }
                                                                value={questionBank.id.toString()}
                                                            >
                                                                {
                                                                    questionBank.title
                                                                }
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label>Question</Label>
                                            <Select
                                                name="bank_question_id"
                                                value={selectedQuestionId}
                                                onValueChange={
                                                    setSelectedQuestionId
                                                }
                                                disabled={!selectedBank}
                                            >
                                                <SelectTrigger className="w-full">
                                                    <SelectValue placeholder="Choose question" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {selectedBank?.questions.map(
                                                        (question) => (
                                                            <SelectItem
                                                                key={
                                                                    question.id
                                                                }
                                                                value={question.id.toString()}
                                                            >
                                                                {
                                                                    question.type_label
                                                                }{' '}
                                                                -{' '}
                                                                {
                                                                    question.points
                                                                }{' '}
                                                                pts
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={
                                                    errors.bank_question_id
                                                }
                                            />
                                        </div>

                                        {selectedQuestion ? (
                                            <div className="rounded-md border border-border p-3 text-sm">
                                                <div className="mb-2 flex flex-wrap gap-2">
                                                    <Badge variant="secondary">
                                                        {
                                                            selectedQuestion.type_label
                                                        }
                                                    </Badge>
                                                    <Badge variant="outline">
                                                        {
                                                            selectedQuestion.grading_mode_label
                                                        }
                                                    </Badge>
                                                    <Badge variant="outline">
                                                        {
                                                            selectedQuestion.difficulty
                                                        }
                                                    </Badge>
                                                </div>
                                                <p className="line-clamp-4">
                                                    {selectedQuestion.prompt}
                                                </p>
                                            </div>
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                No active library questions are
                                                available for import.
                                            </p>
                                        )}

                                        <div className="grid gap-2">
                                            <Label>Target section</Label>
                                            <Select
                                                name="campaign_section_id"
                                                defaultValue={firstSectionId}
                                                disabled={
                                                    campaign.sections.length ===
                                                    0
                                                }
                                            >
                                                <SelectTrigger className="w-full">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {campaign.sections.map(
                                                        (section) => (
                                                            <SelectItem
                                                                key={section.id}
                                                                value={section.id.toString()}
                                                            >
                                                                {section.title}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={
                                                    errors.campaign_section_id
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="import-sort-order">
                                                Order
                                            </Label>
                                            <Input
                                                id="import-sort-order"
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

                                        <div className="flex items-center gap-3 rounded-md border border-border px-3 py-2.5">
                                            <input
                                                type="hidden"
                                                name="is_required"
                                                value="0"
                                            />
                                            <Checkbox
                                                id="import-is-required"
                                                name="is_required"
                                                value="1"
                                                defaultChecked
                                            />
                                            <Label
                                                htmlFor="import-is-required"
                                                className="text-sm font-normal"
                                            >
                                                Required
                                            </Label>
                                        </div>

                                        <Button
                                            type="submit"
                                            disabled={
                                                processing ||
                                                campaign.sections.length ===
                                                    0 ||
                                                !selectedQuestion
                                            }
                                        >
                                            {processing && <Spinner />}
                                            <Plus />
                                            Import question
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </section>

                        <section className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <h2 className="text-base font-medium">
                                Add question
                            </h2>
                            <Form<QuestionFormData>
                                {...CampaignQuestionController.store.form(
                                    campaign.id,
                                )}
                                options={{
                                    preserveScroll: true,
                                }}
                                className="mt-4 space-y-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label>Section</Label>
                                            <Select
                                                name="campaign_section_id"
                                                defaultValue={firstSectionId}
                                                disabled={
                                                    campaign.sections.length ===
                                                    0
                                                }
                                            >
                                                <SelectTrigger className="w-full">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {campaign.sections.map(
                                                        (section) => (
                                                            <SelectItem
                                                                key={section.id}
                                                                value={section.id.toString()}
                                                            >
                                                                {section.title}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={
                                                    errors.campaign_section_id
                                                }
                                            />
                                        </div>

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
                                                        {questionTypes.map(
                                                            (type) => (
                                                                <SelectItem
                                                                    key={
                                                                        type.value
                                                                    }
                                                                    value={
                                                                        type.value
                                                                    }
                                                                >
                                                                    {type.label}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={errors.type}
                                                />
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
                                                                    key={
                                                                        mode.value
                                                                    }
                                                                    value={
                                                                        mode.value
                                                                    }
                                                                >
                                                                    {mode.label}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={
                                                        errors.grading_mode
                                                    }
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
                                            <Label htmlFor="prompt">
                                                Prompt
                                            </Label>
                                            <textarea
                                                id="prompt"
                                                name="prompt"
                                                rows={4}
                                                required
                                                className={textareaClass}
                                            />
                                            <InputError
                                                message={errors.prompt}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="expected_rubric">
                                                Rubric
                                            </Label>
                                            <textarea
                                                id="expected_rubric"
                                                name="expected_rubric"
                                                rows={4}
                                                className={textareaClass}
                                            />
                                            <InputError
                                                message={errors.expected_rubric}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="options_text">
                                                Options
                                            </Label>
                                            <textarea
                                                id="options_text"
                                                name="options_text"
                                                rows={3}
                                                className={textareaClass}
                                            />
                                            <InputError
                                                message={errors.options_text}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="correct_answer_text">
                                                Correct answer
                                            </Label>
                                            <textarea
                                                id="correct_answer_text"
                                                name="correct_answer_text"
                                                rows={3}
                                                className={textareaClass}
                                            />
                                            <InputError
                                                message={
                                                    errors.correct_answer_text
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="skill_tags_text">
                                                Skill tags
                                            </Label>
                                            <textarea
                                                id="skill_tags_text"
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
                                                <Label htmlFor="points">
                                                    Points
                                                </Label>
                                                <Input
                                                    id="points"
                                                    name="points"
                                                    type="number"
                                                    min={1}
                                                    defaultValue={10}
                                                    required
                                                />
                                                <InputError
                                                    message={errors.points}
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="sort_order">
                                                    Order
                                                </Label>
                                                <Input
                                                    id="sort_order"
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
                                                id="is_required"
                                                name="is_required"
                                                value="1"
                                                defaultChecked
                                            />
                                            <Label
                                                htmlFor="is_required"
                                                className="text-sm font-normal"
                                            >
                                                Required
                                            </Label>
                                        </div>

                                        <Button
                                            type="submit"
                                            disabled={
                                                processing ||
                                                campaign.sections.length === 0
                                            }
                                        >
                                            {processing && <Spinner />}
                                            <Plus />
                                            Add question
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </section>
                    </aside>
                </div>
            </div>
        </>
    );
}

function EditCampaignQuestionForm({
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
    return (
        <details className="mt-4 rounded-md border border-border p-3">
            <summary className="cursor-pointer text-sm font-medium">
                Edit question snapshot
            </summary>
            <Form<QuestionFormData>
                {...CampaignQuestionController.update.form.patch([
                    campaignId,
                    question.id,
                ])}
                options={{
                    preserveScroll: true,
                }}
                className="mt-4 space-y-4"
            >
                {({ errors, processing }) => (
                    <>
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
                                        {gradingModeOptions.map((mode) => (
                                            <SelectItem
                                                key={mode.value}
                                                value={mode.value}
                                            >
                                                {mode.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.grading_mode} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor={`edit-prompt-${question.id}`}>
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
                            <Label htmlFor={`edit-rubric-${question.id}`}>
                                Rubric
                            </Label>
                            <textarea
                                id={`edit-rubric-${question.id}`}
                                name="expected_rubric"
                                rows={4}
                                defaultValue={question.expected_rubric ?? ''}
                                className={textareaClass}
                            />
                            <InputError message={errors.expected_rubric} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor={`edit-options-${question.id}`}>
                                Options
                            </Label>
                            <textarea
                                id={`edit-options-${question.id}`}
                                name="options_text"
                                rows={3}
                                defaultValue={question.options.join('\n')}
                                className={textareaClass}
                            />
                            <InputError message={errors.options_text} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor={`edit-answer-${question.id}`}>
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
                            />
                            <InputError message={errors.correct_answer_text} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor={`edit-tags-${question.id}`}>
                                Skill tags
                            </Label>
                            <textarea
                                id={`edit-tags-${question.id}`}
                                name="skill_tags_text"
                                rows={3}
                                defaultValue={question.skill_tags.join('\n')}
                                className={textareaClass}
                            />
                            <InputError message={errors.skill_tags_text} />
                        </div>

                        <div className="grid gap-3 md:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor={`edit-points-${question.id}`}>
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
                                <InputError message={errors.difficulty} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`edit-sort-${question.id}`}>
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
                                <InputError message={errors.sort_order} />
                            </div>
                        </div>

                        <div className="flex items-center gap-3 rounded-md border border-border px-3 py-2.5">
                            <input
                                type="hidden"
                                name="ai_generated"
                                value={question.ai_generated ? '1' : '0'}
                            />
                            <input type="hidden" name="is_required" value="0" />
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

                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Save question
                        </Button>
                    </>
                )}
            </Form>
        </details>
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
