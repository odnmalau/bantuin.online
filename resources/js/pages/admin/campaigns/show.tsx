import { Deferred, Form, Head, router, usePage } from '@inertiajs/react';
import {
    ChevronRight,
    Copy,
    GripVertical,
    Mail,
    MoreHorizontal,
    Plus,
    Share2,
    Sparkles,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import type { CSSProperties } from 'react';
import CampaignAssessmentGenerationController from '@/actions/App/Http/Controllers/Admin/CampaignAssessmentGenerationController';
import CampaignCloneController from '@/actions/App/Http/Controllers/Admin/CampaignCloneController';
import CampaignController from '@/actions/App/Http/Controllers/Admin/CampaignController';
import CampaignQuestionController from '@/actions/App/Http/Controllers/Admin/CampaignQuestionController';
import CampaignQuestionGenerationController from '@/actions/App/Http/Controllers/Admin/CampaignQuestionGenerationController';
import CampaignSectionController from '@/actions/App/Http/Controllers/Admin/CampaignSectionController';
import CampaignStatusController from '@/actions/App/Http/Controllers/Admin/CampaignStatusController';
import CampaignForm from '@/components/admin/campaign-form';
import InputError from '@/components/input-error';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import {
    Card,
    CardAction,
    CardContent,
    CardFooter,
    CardHeader,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
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
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
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
import { ShimmerButton } from '@/components/ui/shimmer-button';
import { Skeleton } from '@/components/ui/skeleton';
import { Slider } from '@/components/ui/slider';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { UnsavedChangesGuard } from '@/hooks/use-unsaved-changes-guard';
import admin from '@/routes/admin';
import type { SharedData } from '@/types';

type CampaignQuestion = {
    id: number;
    campaign_section_id: number;
    type: string;
    type_label: string;
    prompt: string;
    expected_rubric: string | null;
    points: number;
    difficulty: string;
    ai_generated: boolean;
    status: string;
    status_label: string;
    sort_order: number;
};

type CampaignSection = {
    id: number;
    title: string;
    description: string | null;
    duration_minutes: number | null;
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
    status: string;
    status_label: string;
    ai_generation_audit: GenerationAuditEntry[];
    created_by: string | null;
    created_at: string;
    activated_at: string | null;
    draft_questions_count: number;
    approved_questions_count: number;
    can_publish: boolean;
    definition_frozen: boolean;
    can_archive: boolean;
    can_clone: boolean;
    sections: CampaignSection[];
};

type QuestionTypeOption = {
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
    can_revoke: boolean;
    can_resend: boolean;
};

type Props = {
    campaign?: Campaign;
    invitations?: CampaignInvitationRow[];
    questionTypes: QuestionTypeOption[];
};

type SectionFormData = {
    title: string;
    description: string;
    duration_minutes: number;
    weight: number;
};

type QuestionFormData = {
    campaign_section_id: number;
    type: string;
    prompt: string;
    expected_rubric: string;
    points: number;
    difficulty: string;
    ai_generated: boolean;
};

function moveId(ids: number[], sourceId: number, targetId: number): number[] {
    const targetIndex = ids.indexOf(targetId);
    const reordered = ids.filter((id) => id !== sourceId);

    reordered.splice(targetIndex, 0, sourceId);

    return reordered;
}

function submitSectionOrder(campaignId: number, sectionIds: number[]) {
    router.patch(
        CampaignSectionController.reorder.url(campaignId),
        { section_ids: sectionIds },
        { preserveScroll: true },
    );
}

function submitQuestionOrder(
    campaignId: number,
    sectionId: number,
    questionIds: number[],
) {
    router.patch(
        CampaignQuestionController.reorder.url([campaignId, sectionId]),
        { question_ids: questionIds },
        { preserveScroll: true },
    );
}

function PointsField({
    initialValue = 10,
    error,
    id,
}: {
    initialValue?: number;
    error?: string;
    id: string;
}) {
    const presets = [5, 10, 20];
    const [selection, setSelection] = useState(
        presets.includes(initialValue) ? initialValue.toString() : 'custom',
    );
    const [customPoints, setCustomPoints] = useState<number | ''>(
        presets.includes(initialValue) ? '' : initialValue,
    );
    const points =
        selection === 'custom' ? Number(customPoints) : Number(selection);

    return (
        <FieldSet data-invalid={Boolean(error)}>
            <FieldLegend variant="label">Maximum points</FieldLegend>
            <input type="hidden" name="points" value={points} />
            <div className="grid grid-cols-4 gap-2">
                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={selection === 'custom' ? '' : selection}
                    onValueChange={(value) => {
                        if (value) {
                            setSelection(value);
                            setCustomPoints('');
                        }
                    }}
                    className="col-span-3 grid grid-cols-3"
                    aria-invalid={Boolean(error)}
                >
                    <ToggleGroupItem value="5">5</ToggleGroupItem>
                    <ToggleGroupItem value="10">10</ToggleGroupItem>
                    <ToggleGroupItem value="20">20</ToggleGroupItem>
                </ToggleGroup>
                <Input
                    id={id}
                    type="number"
                    inputMode="numeric"
                    min={1}
                    max={100}
                    value={customPoints}
                    placeholder="Custom"
                    aria-label="Custom maximum points"
                    onFocus={() => setSelection('custom')}
                    onChange={(event) => {
                        setSelection('custom');
                        setCustomPoints(
                            event.target.value === ''
                                ? ''
                                : Number(event.target.value),
                        );
                    }}
                    aria-invalid={Boolean(error)}
                    required={selection === 'custom'}
                />
            </div>
            <FieldError>{error}</FieldError>
        </FieldSet>
    );
}

function ScoreContributionField({
    initialValue,
    maximum,
    singleSection,
    error,
    id,
}: {
    initialValue: number;
    maximum: number;
    singleSection: boolean;
    error?: string;
    id: string;
}) {
    const [value, setValue] = useState(singleSection ? 100 : initialValue);

    return (
        <Field data-invalid={Boolean(error)} data-disabled={singleSection}>
            <div className="flex items-center justify-between gap-3">
                <FieldLabel htmlFor={id}>Score contribution</FieldLabel>
                <span className="text-sm tabular-nums">{value}%</span>
            </div>
            <input type="hidden" name="weight" value={value} />
            <Slider
                id={id}
                value={[value]}
                onValueChange={([nextValue]) => setValue(nextValue)}
                min={1}
                max={maximum}
                step={1}
                disabled={singleSection}
                aria-invalid={Boolean(error)}
            />
            <FieldError>{error}</FieldError>
        </Field>
    );
}

function focusFirstError(errors: Record<string, string | undefined>) {
    window.requestAnimationFrame(() => {
        const invalidControl = document.querySelector<HTMLElement>(
            '[aria-invalid="true"]:not([type="hidden"])',
        );

        if (invalidControl) {
            invalidControl.focus();

            return;
        }

        for (const key of Object.keys(errors)) {
            const [root, ...nested] = key.split('.');
            const name = nested.reduce(
                (fieldName, segment) => `${fieldName}[${segment}]`,
                root,
            );
            const control = document.querySelector<HTMLElement>(
                `[name="${CSS.escape(name)}"]:not([type="hidden"])`,
            );

            if (control) {
                control.focus();

                return;
            }
        }
    });
}

function updateDisclosureQuery(
    pageUrl: string,
    key: string,
    value: string | null,
) {
    const url = new URL(pageUrl, 'http://localhost');

    if (value === null) {
        url.searchParams.delete(key);
    } else {
        url.searchParams.set(key, value);
    }

    router.push({
        url: `${url.pathname}${url.search}${url.hash}`,
        preserveScroll: true,
        preserveState: true,
    });
}

function normalizeLocale(locale: string): string {
    try {
        return Intl.getCanonicalLocales(locale.replaceAll('_', '-'))[0] ?? 'en';
    } catch {
        return 'en';
    }
}

function CampaignDetailSkeleton() {
    return (
        <div role="status" aria-busy="true" className="flex flex-col gap-6">
            <span className="sr-only">Loading campaign details…</span>

            <Card className="gap-0 bg-background-200">
                <CardHeader>
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <Skeleton className="h-6 w-full max-w-64" />
                        <div className="flex flex-wrap items-center gap-2 md:justify-end">
                            <Skeleton className="h-10 w-24" />
                            <Skeleton className="h-10 w-24" />
                            <Skeleton className="size-10" />
                        </div>
                    </div>
                </CardHeader>
            </Card>

            <Card className="gap-0">
                <CardHeader className="border-b">
                    <Skeleton className="h-5 w-full max-w-48" />
                    <CardAction className="flex flex-wrap items-center gap-2">
                        <Skeleton className="h-10 w-36" />
                        <Skeleton className="h-10 w-40" />
                    </CardAction>
                </CardHeader>
                <CardContent className="bg-background-200 p-0">
                    {Array.from({ length: 2 }).map((_, index) => (
                        <div key={index} className="not-last:border-b">
                            <div className="flex items-start gap-3 px-(--card-spacing) py-4">
                                <Skeleton className="mt-1 size-4 shrink-0" />
                                <div className="flex min-w-0 flex-1 flex-col gap-2">
                                    <Skeleton className="h-5 w-full max-w-40" />
                                    <Skeleton className="h-4 w-full max-w-56" />
                                </div>
                                <Skeleton className="size-10 shrink-0" />
                            </div>

                            {index === 0 ? (
                                <div className="px-(--card-spacing) pb-4">
                                    <ItemGroup className="gap-0 overflow-hidden rounded-md border">
                                        {Array.from({ length: 2 }).map(
                                            (__, questionIndex) => (
                                                <Fragment key={questionIndex}>
                                                    {questionIndex > 0 ? (
                                                        <ItemSeparator className="my-0" />
                                                    ) : null}
                                                    <Item
                                                        size="sm"
                                                        className="rounded-none border-0"
                                                    >
                                                        <ItemMedia className="w-auto self-start pt-0.5">
                                                            <Skeleton className="size-4" />
                                                        </ItemMedia>
                                                        <ItemContent className="min-w-0">
                                                            <Skeleton className="h-4 w-full max-w-96" />
                                                            <div className="flex flex-wrap gap-2">
                                                                <Skeleton className="h-4 w-16" />
                                                                <Skeleton className="h-4 w-20" />
                                                            </div>
                                                        </ItemContent>
                                                        <ItemActions className="self-start">
                                                            <Skeleton className="size-10" />
                                                        </ItemActions>
                                                    </Item>
                                                </Fragment>
                                            ),
                                        )}
                                    </ItemGroup>
                                    <div className="mt-3 flex justify-end">
                                        <Skeleton className="h-10 w-44" />
                                    </div>
                                </div>
                            ) : null}
                        </div>
                    ))}
                </CardContent>
                <CardFooter className="-mb-(--card-spacing) flex-col items-stretch gap-3 border-t bg-background py-(--card-spacing) sm:flex-row sm:items-center sm:justify-between">
                    <Skeleton className="h-4 w-full max-w-72" />
                    <Skeleton className="h-10 w-28 shrink-0" />
                </CardFooter>
            </Card>
        </div>
    );
}

export default function AdminCampaignsShow({
    campaign,
    invitations,
    questionTypes,
}: Props) {
    const page = usePage<
        SharedData & { flash?: { campaign_invite_url?: string } }
    >();
    const latestInviteUrl = page.props.flash?.campaign_invite_url;
    const { auth } = page.props;
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [isGeneratingAssessment, setIsGeneratingAssessment] = useState(false);
    const [draggedSectionId, setDraggedSectionId] = useState<number | null>(
        null,
    );
    const [draggedQuestion, setDraggedQuestion] = useState<{
        sectionId: number;
        questionId: number;
    } | null>(null);
    const currentUrl = new URL(page.url, 'http://localhost');
    const requestedSection = currentUrl.searchParams.get('section');
    const numberFormatter = new Intl.NumberFormat(
        normalizeLocale(page.props.locale),
    );

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
                            {campaign.definition_frozen ? (
                                <Card className="border-amber-500/30 bg-amber-500/5">
                                    <CardContent className="flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                                        <p>
                                            This campaign definition is frozen
                                            because candidates have already been
                                            invited. Clone it as a new draft to
                                            revise questions, ranking, or role
                                            context.
                                        </p>
                                        {campaign.can_clone &&
                                        !auth.readOnly ? (
                                            <Form
                                                {...CampaignCloneController.store.form(
                                                    campaign.id,
                                                )}
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        size="default"
                                                        variant="outline"
                                                        disabled={processing}
                                                    >
                                                        {processing && (
                                                            <Spinner />
                                                        )}
                                                        <Copy
                                                            aria-hidden="true"
                                                            data-icon="inline-start"
                                                        />
                                                        Clone as New Draft
                                                    </Button>
                                                )}
                                            </Form>
                                        ) : null}
                                    </CardContent>
                                </Card>
                            ) : null}
                            <CampaignOverviewCard
                                campaign={campaign}
                                invitations={invitations}
                                latestInviteUrl={latestInviteUrl}
                                deleteDialogOpen={deleteDialogOpen}
                                onDeleteDialogOpenChange={setDeleteDialogOpen}
                            />

                            <div
                                className={`flex flex-col gap-6 ${campaign.definition_frozen ? '[&_form]:pointer-events-none [&_form]:opacity-60' : ''}`}
                            >
                                <Card
                                    className="gap-0 overflow-hidden"
                                    aria-busy={isGeneratingAssessment}
                                >
                                    <CardHeader className="border-b">
                                        <h2 className="scroll-mt-24 font-heading text-heading-16 text-balance">
                                            Sections &amp; Questions
                                        </h2>
                                        {!campaign.definition_frozen ? (
                                            <CardAction className="flex flex-wrap items-center gap-2">
                                                <GenerateAssessmentButton
                                                    campaignId={campaign.id}
                                                    disabled={
                                                        campaign.draft_questions_count >
                                                        0
                                                    }
                                                    onProcessingChange={
                                                        setIsGeneratingAssessment
                                                    }
                                                />
                                                {campaign.draft_questions_count >
                                                0 ? (
                                                    <>
                                                        <Form
                                                            {...CampaignQuestionController.approveAll.form(
                                                                campaign.id,
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
                                                                    variant="outline"
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                >
                                                                    {processing && (
                                                                        <Spinner />
                                                                    )}
                                                                    Approve
                                                                </Button>
                                                            )}
                                                        </Form>
                                                        <Form
                                                            {...CampaignQuestionController.discardAll.form.delete(
                                                                campaign.id,
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
                                                                    variant="destructive"
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                >
                                                                    {processing && (
                                                                        <Spinner />
                                                                    )}
                                                                    Discard
                                                                </Button>
                                                            )}
                                                        </Form>
                                                    </>
                                                ) : null}
                                            </CardAction>
                                        ) : null}
                                    </CardHeader>

                                    <CardContent
                                        style={
                                            isGeneratingAssessment
                                                ? ({
                                                      '--spread': '90deg',
                                                      '--shimmer-color':
                                                          'var(--foreground)',
                                                      '--radius': '0px',
                                                      '--speed': '3s',
                                                      '--cut': '2px',
                                                  } as CSSProperties)
                                                : undefined
                                        }
                                        className="relative z-10 overflow-hidden bg-background-200 p-0"
                                    >
                                        {isGeneratingAssessment ? (
                                            <div
                                                aria-hidden="true"
                                                className="assessment-content-shimmer"
                                            />
                                        ) : null}
                                        <div>
                                            {campaign.sections.length === 0 ? (
                                                <Empty className="rounded-none p-8">
                                                    <EmptyHeader>
                                                        <EmptyTitle className="text-base">
                                                            No sections yet
                                                        </EmptyTitle>
                                                        <EmptyDescription>
                                                            Add a section to
                                                            group questions by
                                                            topic or score
                                                            contribution.
                                                        </EmptyDescription>
                                                    </EmptyHeader>
                                                </Empty>
                                            ) : (
                                                <Accordion
                                                    type="single"
                                                    collapsible
                                                    value={
                                                        campaign.sections.some(
                                                            ({ id }) =>
                                                                id.toString() ===
                                                                requestedSection,
                                                        )
                                                            ? (requestedSection ??
                                                              '')
                                                            : campaign.sections[0].id.toString()
                                                    }
                                                    onValueChange={(value) =>
                                                        updateDisclosureQuery(
                                                            page.url,
                                                            'section',
                                                            value || null,
                                                        )
                                                    }
                                                >
                                                    {campaign.sections.map(
                                                        (section) => (
                                                            <AccordionItem
                                                                key={section.id}
                                                                value={section.id.toString()}
                                                                className="relative"
                                                                onDragOver={(
                                                                    event,
                                                                ) => {
                                                                    if (
                                                                        draggedSectionId !==
                                                                        null
                                                                    ) {
                                                                        event.preventDefault();
                                                                    }
                                                                }}
                                                                onDrop={(
                                                                    event,
                                                                ) => {
                                                                    event.preventDefault();

                                                                    if (
                                                                        draggedSectionId ===
                                                                            null ||
                                                                        draggedSectionId ===
                                                                            section.id
                                                                    ) {
                                                                        return;
                                                                    }

                                                                    submitSectionOrder(
                                                                        campaign.id,
                                                                        moveId(
                                                                            campaign.sections.map(
                                                                                ({
                                                                                    id,
                                                                                }) =>
                                                                                    id,
                                                                            ),
                                                                            draggedSectionId,
                                                                            section.id,
                                                                        ),
                                                                    );
                                                                    setDraggedSectionId(
                                                                        null,
                                                                    );
                                                                }}
                                                            >
                                                                {!campaign.definition_frozen ? (
                                                                    <button
                                                                        type="button"
                                                                        draggable
                                                                        aria-label={`Reorder ${section.title}`}
                                                                        className="absolute top-4 left-3 cursor-grab touch-none text-muted-foreground active:cursor-grabbing"
                                                                        onDragStart={() =>
                                                                            setDraggedSectionId(
                                                                                section.id,
                                                                            )
                                                                        }
                                                                        onDragEnd={() =>
                                                                            setDraggedSectionId(
                                                                                null,
                                                                            )
                                                                        }
                                                                    >
                                                                        <GripVertical
                                                                            aria-hidden="true"
                                                                            className="size-4"
                                                                        />
                                                                    </button>
                                                                ) : null}
                                                                <AccordionTrigger className="pr-(--card-spacing) pl-12 hover:no-underline [&>[data-slot=accordion-trigger-icon]]:hidden">
                                                                    <div className="flex min-w-0 flex-1 flex-col gap-1 pr-12">
                                                                        <span className="line-clamp-1 text-base font-medium">
                                                                            {
                                                                                section.title
                                                                            }
                                                                        </span>
                                                                        <span className="flex flex-wrap items-center gap-x-1.5 text-xs font-normal text-muted-foreground">
                                                                            <span>
                                                                                {numberFormatter.format(
                                                                                    section
                                                                                        .questions
                                                                                        .length,
                                                                                )}{' '}
                                                                                {section
                                                                                    .questions
                                                                                    .length ===
                                                                                1
                                                                                    ? 'question'
                                                                                    : 'questions'}
                                                                            </span>
                                                                            {section.duration_minutes ? (
                                                                                <>
                                                                                    <span aria-hidden="true">
                                                                                        ·
                                                                                    </span>
                                                                                    <span>
                                                                                        {numberFormatter.format(
                                                                                            section.duration_minutes,
                                                                                        )}
                                                                                        &nbsp;min
                                                                                    </span>
                                                                                </>
                                                                            ) : null}
                                                                            <span aria-hidden="true">
                                                                                ·
                                                                            </span>
                                                                            <span>
                                                                                {numberFormatter.format(
                                                                                    section.weight,
                                                                                )}

                                                                                %
                                                                                score
                                                                                contribution
                                                                            </span>
                                                                        </span>
                                                                    </div>
                                                                </AccordionTrigger>

                                                                <div className="absolute top-3 right-3">
                                                                    <SectionActions
                                                                        campaignId={
                                                                            campaign.id
                                                                        }
                                                                        section={
                                                                            section
                                                                        }
                                                                        sectionCount={
                                                                            campaign
                                                                                .sections
                                                                                .length
                                                                        }
                                                                    />
                                                                </div>

                                                                <AccordionContent className="px-(--card-spacing) [&_p:not(:last-child)]:mb-0">
                                                                    {section
                                                                        .questions
                                                                        .length ===
                                                                    0 ? (
                                                                        <Empty className="border p-8">
                                                                            <EmptyHeader>
                                                                                <EmptyTitle className="text-base">
                                                                                    {
                                                                                        'No questions yet'
                                                                                    }
                                                                                </EmptyTitle>
                                                                                <EmptyDescription>
                                                                                    {
                                                                                        'Use the Add Question button to add the first question.'
                                                                                    }
                                                                                </EmptyDescription>
                                                                            </EmptyHeader>
                                                                        </Empty>
                                                                    ) : (
                                                                        <ItemGroup className="gap-0 overflow-hidden rounded-md border">
                                                                            {section.questions.map(
                                                                                (
                                                                                    question,
                                                                                    questionIndex,
                                                                                ) => (
                                                                                    <Fragment
                                                                                        key={
                                                                                            question.id
                                                                                        }
                                                                                    >
                                                                                        {questionIndex >
                                                                                        0 ? (
                                                                                            <ItemSeparator className="my-0" />
                                                                                        ) : null}
                                                                                        <Item
                                                                                            size="sm"
                                                                                            className="rounded-none border-0 [contain-intrinsic-size:auto_64px] [content-visibility:auto]"
                                                                                            onDragOver={(
                                                                                                event,
                                                                                            ) => {
                                                                                                if (
                                                                                                    draggedQuestion?.sectionId ===
                                                                                                    section.id
                                                                                                ) {
                                                                                                    event.preventDefault();
                                                                                                }
                                                                                            }}
                                                                                            onDrop={(
                                                                                                event,
                                                                                            ) => {
                                                                                                event.preventDefault();

                                                                                                if (
                                                                                                    draggedQuestion ===
                                                                                                        null ||
                                                                                                    draggedQuestion.sectionId !==
                                                                                                        section.id ||
                                                                                                    draggedQuestion.questionId ===
                                                                                                        question.id
                                                                                                ) {
                                                                                                    return;
                                                                                                }

                                                                                                submitQuestionOrder(
                                                                                                    campaign.id,
                                                                                                    section.id,
                                                                                                    moveId(
                                                                                                        section.questions.map(
                                                                                                            ({
                                                                                                                id,
                                                                                                            }) =>
                                                                                                                id,
                                                                                                        ),
                                                                                                        draggedQuestion.questionId,
                                                                                                        question.id,
                                                                                                    ),
                                                                                                );
                                                                                                setDraggedQuestion(
                                                                                                    null,
                                                                                                );
                                                                                            }}
                                                                                        >
                                                                                            <ItemMedia className="w-auto gap-1 self-start pt-0.5 text-xs text-muted-foreground tabular-nums">
                                                                                                {!campaign.definition_frozen ? (
                                                                                                    <button
                                                                                                        type="button"
                                                                                                        draggable
                                                                                                        aria-label={`Reorder question ${questionIndex + 1}`}
                                                                                                        className="cursor-grab touch-none active:cursor-grabbing"
                                                                                                        onDragStart={() =>
                                                                                                            setDraggedQuestion(
                                                                                                                {
                                                                                                                    sectionId:
                                                                                                                        section.id,
                                                                                                                    questionId:
                                                                                                                        question.id,
                                                                                                                },
                                                                                                            )
                                                                                                        }
                                                                                                        onDragEnd={() =>
                                                                                                            setDraggedQuestion(
                                                                                                                null,
                                                                                                            )
                                                                                                        }
                                                                                                    >
                                                                                                        <GripVertical
                                                                                                            aria-hidden="true"
                                                                                                            className="size-4"
                                                                                                        />
                                                                                                    </button>
                                                                                                ) : null}
                                                                                                {String(
                                                                                                    questionIndex +
                                                                                                        1,
                                                                                                ).padStart(
                                                                                                    2,
                                                                                                    '0',
                                                                                                )}
                                                                                            </ItemMedia>
                                                                                            <ItemContent className="min-w-0">
                                                                                                <ItemTitle className="line-clamp-2 w-full">
                                                                                                    {
                                                                                                        question.prompt
                                                                                                    }
                                                                                                </ItemTitle>
                                                                                                <ItemDescription className="flex flex-wrap items-center gap-x-1.5 gap-y-1">
                                                                                                    <span>
                                                                                                        {
                                                                                                            question.type_label
                                                                                                        }
                                                                                                    </span>
                                                                                                    <span aria-hidden="true">
                                                                                                        ·
                                                                                                    </span>
                                                                                                    <span>
                                                                                                        {numberFormatter.format(
                                                                                                            question.points,
                                                                                                        )}
                                                                                                        &nbsp;pts
                                                                                                    </span>
                                                                                                    {question.status !==
                                                                                                    'approved' ? (
                                                                                                        <Badge variant="outline">
                                                                                                            {
                                                                                                                question.status_label
                                                                                                            }
                                                                                                        </Badge>
                                                                                                    ) : null}
                                                                                                </ItemDescription>
                                                                                            </ItemContent>
                                                                                            <ItemActions className="self-start">
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
                                                                                                />
                                                                                            </ItemActions>
                                                                                        </Item>
                                                                                    </Fragment>
                                                                                ),
                                                                            )}
                                                                        </ItemGroup>
                                                                    )}
                                                                    <AddQuestionAction
                                                                        campaignId={
                                                                            campaign.id
                                                                        }
                                                                        section={
                                                                            section
                                                                        }
                                                                        questionTypes={
                                                                            questionTypes
                                                                        }
                                                                        generationDisabled={
                                                                            campaign.draft_questions_count >
                                                                            0
                                                                        }
                                                                    />
                                                                </AccordionContent>
                                                            </AccordionItem>
                                                        ),
                                                    )}
                                                </Accordion>
                                            )}
                                        </div>
                                    </CardContent>

                                    <CardFooter className="-mb-(--card-spacing) justify-between gap-3 border-t bg-background py-(--card-spacing)">
                                        <p className="text-sm text-muted-foreground">
                                            Add another section when the
                                            assessment needs a separate topic or
                                            scoring group.
                                        </p>
                                        {!campaign.definition_frozen ? (
                                            <AddSectionSheet
                                                campaignId={campaign.id}
                                                sectionCount={
                                                    campaign.sections.length
                                                }
                                                disabled={
                                                    campaign.draft_questions_count >
                                                    0
                                                }
                                            />
                                        ) : null}
                                    </CardFooter>
                                </Card>
                            </div>
                        </>
                    ) : null}
                </Deferred>
            </div>
        </>
    );
}

function GenerateAssessmentButton({
    campaignId,
    disabled,
    onProcessingChange,
}: {
    campaignId: number;
    disabled: boolean;
    onProcessingChange: (processing: boolean) => void;
}) {
    return (
        <Form
            {...CampaignAssessmentGenerationController.store.form(campaignId)}
            options={{ preserveScroll: true }}
            onStart={() => onProcessingChange(true)}
            onFinish={() => onProcessingChange(false)}
            className="flex flex-col items-end gap-1"
        >
            {({ errors, processing }) => (
                <>
                    <ShimmerButton
                        type="submit"
                        variant="outline"
                        size="default"
                        disabled={disabled || processing}
                    >
                        {processing ? (
                            <Spinner />
                        ) : (
                            <Sparkles
                                aria-hidden="true"
                                data-icon="inline-start"
                            />
                        )}
                        {processing
                            ? 'Generating Assessment…'
                            : 'Generate Assessment'}
                    </ShimmerButton>
                    <InputError message={errors.generation} />
                </>
            )}
        </Form>
    );
}

function AddSectionSheet({
    campaignId,
    sectionCount,
    disabled,
}: {
    campaignId: number;
    sectionCount: number;
    disabled: boolean;
}) {
    const [open, setOpen] = useState(false);
    const maximumContribution = Math.max(1, 100 - sectionCount);

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button variant="outline" size="default" disabled={disabled}>
                    <Plus aria-hidden="true" data-icon="inline-start" />
                    Add Section…
                </Button>
            </SheetTrigger>
            <SheetContent className="bg-card text-card-foreground data-[side=right]:sm:max-w-2xl">
                <SheetHeader>
                    <SheetTitle>Add Section</SheetTitle>
                    <SheetDescription>
                        Create a new section for grouping campaign questions.
                    </SheetDescription>
                </SheetHeader>

                <Form<SectionFormData>
                    {...CampaignSectionController.store.form(campaignId)}
                    options={{
                        preserveScroll: true,
                    }}
                    onSuccess={() => setOpen(false)}
                    onError={focusFirstError}
                    className="flex flex-1 flex-col overflow-hidden"
                >
                    {({ errors, isDirty, processing }) => (
                        <>
                            <UnsavedChangesGuard
                                active={isDirty && !processing}
                            />
                            <FieldGroup className="flex-1 overflow-y-auto px-4">
                                <Field data-invalid={Boolean(errors.title)}>
                                    <FieldLabel htmlFor="section-title">
                                        Title
                                    </FieldLabel>
                                    <Input
                                        id="section-title"
                                        name="title"
                                        autoComplete="off"
                                        aria-invalid={Boolean(errors.title)}
                                        required
                                    />
                                    <FieldError>{errors.title}</FieldError>
                                </Field>

                                <Field
                                    data-invalid={Boolean(errors.description)}
                                >
                                    <FieldLabel htmlFor="section-description">
                                        Description
                                    </FieldLabel>
                                    <Textarea
                                        id="section-description"
                                        name="description"
                                        autoComplete="off"
                                        rows={4}
                                        className="min-h-28"
                                        aria-invalid={Boolean(
                                            errors.description,
                                        )}
                                    />
                                    <FieldError>
                                        {errors.description}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={Boolean(
                                        errors.duration_minutes,
                                    )}
                                >
                                    <FieldLabel htmlFor="duration_minutes">
                                        Duration (minutes)
                                    </FieldLabel>
                                    <Input
                                        id="duration_minutes"
                                        name="duration_minutes"
                                        type="number"
                                        inputMode="numeric"
                                        autoComplete="off"
                                        min={1}
                                        max={480}
                                        aria-invalid={Boolean(
                                            errors.duration_minutes,
                                        )}
                                    />
                                    <FieldError>
                                        {errors.duration_minutes}
                                    </FieldError>
                                </Field>

                                <ScoreContributionField
                                    id="section-weight"
                                    initialValue={Math.min(
                                        sectionCount === 0 ? 100 : 20,
                                        maximumContribution,
                                    )}
                                    maximum={maximumContribution}
                                    singleSection={sectionCount === 0}
                                    error={errors.weight}
                                />
                            </FieldGroup>

                            <SheetFooter className="flex-row items-center justify-between gap-3 border-t bg-background sm:flex-row">
                                <SheetClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="default"
                                    >
                                        Cancel
                                    </Button>
                                </SheetClose>
                                <Button
                                    type="submit"
                                    size="default"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    <Plus
                                        aria-hidden="true"
                                        data-icon="inline-start"
                                    />
                                    Add Section
                                </Button>
                            </SheetFooter>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}

function SectionActions({
    campaignId,
    section,
    sectionCount,
}: {
    campaignId: number;
    section: CampaignSection;
    sectionCount: number;
}) {
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    return (
        <>
            <div className="flex items-center">
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            size="icon"
                            variant="ghost"
                            aria-label="Open section actions"
                        >
                            <MoreHorizontal
                                aria-hidden="true"
                                data-icon="inline-start"
                            />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-40">
                        <DropdownMenuGroup>
                            <DropdownMenuItem
                                onSelect={(event) => {
                                    event.preventDefault();
                                    setEditOpen(true);
                                }}
                            >
                                Edit Section…
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
            </div>

            <EditSectionSheet
                campaignId={campaignId}
                section={section}
                sectionCount={sectionCount}
                open={editOpen}
                onOpenChange={setEditOpen}
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

function AddQuestionAction({
    campaignId,
    section,
    questionTypes,
    generationDisabled,
}: {
    campaignId: number;
    section: CampaignSection;
    questionTypes: QuestionTypeOption[];
    generationDisabled: boolean;
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Form
                {...CampaignQuestionGenerationController.form([
                    campaignId,
                    section.id,
                ])}
                options={{ preserveScroll: true }}
                className="mt-3 flex flex-col items-end gap-1"
            >
                {({ errors, processing }) => (
                    <>
                        <ButtonGroup aria-label="Question actions">
                            <Button
                                type="button"
                                size="default"
                                variant="outline"
                                disabled={generationDisabled}
                                onClick={() => setOpen(true)}
                            >
                                <Plus
                                    aria-hidden="true"
                                    data-icon="inline-start"
                                />
                                Add Question…
                            </Button>
                            <Button
                                type="submit"
                                size="icon"
                                variant="outline"
                                aria-label="Generate question with AI"
                                disabled={generationDisabled || processing}
                            >
                                {processing ? (
                                    <Spinner aria-hidden="true" />
                                ) : (
                                    <Sparkles aria-hidden="true" />
                                )}
                            </Button>
                        </ButtonGroup>
                        <InputError
                            message={errors[`generation.${section.id}`]}
                        />
                    </>
                )}
            </Form>
            <AddQuestionSheet
                campaignId={campaignId}
                section={section}
                questionTypes={questionTypes}
                open={open}
                onOpenChange={setOpen}
            />
        </>
    );
}

function EditSectionSheet({
    campaignId,
    section,
    sectionCount,
    open,
    onOpenChange,
}: {
    campaignId: number;
    section: CampaignSection;
    sectionCount: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="bg-card text-card-foreground data-[side=right]:sm:max-w-2xl">
                <SheetHeader>
                    <SheetTitle>Edit Section</SheetTitle>
                    <SheetDescription>
                        Update how this section is presented, timed, and scored.
                    </SheetDescription>
                </SheetHeader>

                <Form<SectionFormData>
                    {...CampaignSectionController.update.form.patch([
                        campaignId,
                        section.id,
                    ])}
                    options={{
                        preserveScroll: true,
                    }}
                    onSuccess={() => onOpenChange(false)}
                    onError={focusFirstError}
                    className="flex flex-1 flex-col overflow-hidden"
                >
                    {({ errors, isDirty, processing }) => (
                        <>
                            <UnsavedChangesGuard
                                active={isDirty && !processing}
                            />
                            <FieldGroup className="flex-1 overflow-y-auto px-4">
                                <Field data-invalid={Boolean(errors.title)}>
                                    <FieldLabel
                                        htmlFor={`edit-section-title-${section.id}`}
                                    >
                                        Title
                                    </FieldLabel>
                                    <Input
                                        id={`edit-section-title-${section.id}`}
                                        name="title"
                                        autoComplete="off"
                                        defaultValue={section.title}
                                        aria-invalid={Boolean(errors.title)}
                                        required
                                    />
                                    <FieldError>{errors.title}</FieldError>
                                </Field>

                                <Field
                                    data-invalid={Boolean(errors.description)}
                                >
                                    <FieldLabel
                                        htmlFor={`edit-section-description-${section.id}`}
                                    >
                                        Description
                                    </FieldLabel>
                                    <Textarea
                                        id={`edit-section-description-${section.id}`}
                                        name="description"
                                        autoComplete="off"
                                        rows={4}
                                        defaultValue={section.description ?? ''}
                                        className="min-h-28"
                                        aria-invalid={Boolean(
                                            errors.description,
                                        )}
                                    />
                                    <FieldError>
                                        {errors.description}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={Boolean(
                                        errors.duration_minutes,
                                    )}
                                >
                                    <FieldLabel
                                        htmlFor={`edit-section-duration-${section.id}`}
                                    >
                                        Duration (minutes)
                                    </FieldLabel>
                                    <Input
                                        id={`edit-section-duration-${section.id}`}
                                        name="duration_minutes"
                                        type="number"
                                        inputMode="numeric"
                                        autoComplete="off"
                                        min={1}
                                        max={480}
                                        defaultValue={
                                            section.duration_minutes ?? ''
                                        }
                                        aria-invalid={Boolean(
                                            errors.duration_minutes,
                                        )}
                                    />
                                    <FieldError>
                                        {errors.duration_minutes}
                                    </FieldError>
                                </Field>

                                <ScoreContributionField
                                    id={`edit-section-weight-${section.id}`}
                                    initialValue={section.weight}
                                    maximum={Math.max(1, 101 - sectionCount)}
                                    singleSection={sectionCount === 1}
                                    error={errors.weight}
                                />
                            </FieldGroup>

                            <SheetFooter className="flex-row items-center justify-between gap-3 border-t bg-background sm:flex-row">
                                <SheetClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="default"
                                    >
                                        Cancel
                                    </Button>
                                </SheetClose>
                                <Button
                                    type="submit"
                                    size="default"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Save Changes
                                </Button>
                            </SheetFooter>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
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
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Section?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This will permanently delete “{section.title}” and its
                        questions. This action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>

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

                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    Delete Section
                                </Button>
                            </AlertDialogFooter>
                        </>
                    )}
                </Form>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function AddQuestionSheet({
    campaignId,
    section,
    questionTypes,
    open,
    onOpenChange,
}: {
    campaignId: number;
    section: CampaignSection;
    questionTypes: QuestionTypeOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="bg-card text-card-foreground data-[side=right]:sm:max-w-2xl">
                <SheetHeader>
                    <SheetTitle>Add Question</SheetTitle>
                    <SheetDescription>
                        Add a question to {section.title}.
                    </SheetDescription>
                </SheetHeader>

                <Form<QuestionFormData>
                    {...CampaignQuestionController.store.form(campaignId)}
                    options={{
                        preserveScroll: true,
                    }}
                    onSuccess={() => onOpenChange(false)}
                    onError={focusFirstError}
                    className="flex flex-1 flex-col overflow-hidden"
                >
                    {({ errors, isDirty, processing }) => (
                        <>
                            <UnsavedChangesGuard
                                active={isDirty && !processing}
                            />
                            <FieldGroup className="flex-1 overflow-y-auto px-4">
                                <input
                                    type="hidden"
                                    name="campaign_section_id"
                                    value={section.id}
                                />
                                <input
                                    type="hidden"
                                    name="ai_generated"
                                    value="0"
                                />
                                <FieldError>
                                    {errors.campaign_section_id}
                                </FieldError>

                                <Field data-invalid={Boolean(errors.type)}>
                                    <FieldLabel htmlFor={`type-${section.id}`}>
                                        Type
                                    </FieldLabel>
                                    <Select
                                        name="type"
                                        defaultValue="long_text"
                                    >
                                        <SelectTrigger
                                            id={`type-${section.id}`}
                                            className="w-full"
                                            aria-invalid={Boolean(errors.type)}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {questionTypes.map((type) => (
                                                    <SelectItem
                                                        key={type.value}
                                                        value={type.value}
                                                    >
                                                        {type.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                    <FieldError>{errors.type}</FieldError>
                                </Field>
                                <Field
                                    data-invalid={Boolean(errors.difficulty)}
                                >
                                    <FieldLabel
                                        htmlFor={`difficulty-${section.id}`}
                                    >
                                        Difficulty
                                    </FieldLabel>
                                    <Select
                                        name="difficulty"
                                        defaultValue="medium"
                                    >
                                        <SelectTrigger
                                            id={`difficulty-${section.id}`}
                                            className="w-full"
                                            aria-invalid={Boolean(
                                                errors.difficulty,
                                            )}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
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
                                    <FieldError>{errors.difficulty}</FieldError>
                                </Field>

                                <Field data-invalid={Boolean(errors.prompt)}>
                                    <FieldLabel
                                        htmlFor={`prompt-${section.id}`}
                                    >
                                        Question
                                    </FieldLabel>
                                    <Textarea
                                        id={`prompt-${section.id}`}
                                        name="prompt"
                                        autoComplete="off"
                                        rows={4}
                                        required
                                        className="min-h-28"
                                        aria-invalid={Boolean(errors.prompt)}
                                    />
                                    <FieldError>{errors.prompt}</FieldError>
                                </Field>

                                <Field
                                    data-invalid={Boolean(
                                        errors.expected_rubric,
                                    )}
                                >
                                    <FieldLabel
                                        htmlFor={`expected-rubric-${section.id}`}
                                    >
                                        Expected answer / scoring guide
                                    </FieldLabel>
                                    <Textarea
                                        id={`expected-rubric-${section.id}`}
                                        name="expected_rubric"
                                        autoComplete="off"
                                        rows={4}
                                        required
                                        className="min-h-28"
                                        aria-invalid={Boolean(
                                            errors.expected_rubric,
                                        )}
                                    />
                                    <FieldError>
                                        {errors.expected_rubric}
                                    </FieldError>
                                </Field>

                                <PointsField
                                    id={`points-${section.id}`}
                                    error={errors.points}
                                />
                            </FieldGroup>

                            <SheetFooter className="flex-row items-center justify-between gap-3 border-t bg-background sm:flex-row">
                                <SheetClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="default"
                                    >
                                        Cancel
                                    </Button>
                                </SheetClose>
                                <Button
                                    type="submit"
                                    size="default"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    <Plus
                                        aria-hidden="true"
                                        data-icon="inline-start"
                                    />
                                    Add Question
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
    const page = usePage<SharedData>();
    const isOpen =
        new URL(page.url, 'http://localhost').searchParams.get('overview') ===
        'open';
    const numberFormatter = new Intl.NumberFormat(
        normalizeLocale(page.props.locale),
    );

    return (
        <Collapsible
            className="group/collapsible"
            open={isOpen}
            onOpenChange={(open) =>
                updateDisclosureQuery(
                    page.url,
                    'overview',
                    open ? 'open' : null,
                )
            }
        >
            <Card className="gap-0 bg-background-200">
                <CardHeader>
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <CollapsibleTrigger asChild>
                            <button
                                type="button"
                                className="flex min-w-0 cursor-pointer touch-manipulation items-center gap-2 rounded-md text-left hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                            >
                                <ChevronRight
                                    aria-hidden="true"
                                    className="size-4 shrink-0 text-muted-foreground transition-transform group-data-[state=open]/collapsible:rotate-90"
                                />
                                <h1 className="min-w-0 scroll-mt-24 font-heading text-heading-16 text-balance break-words">
                                    {campaign.title}
                                </h1>
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
                                value={campaign.seniority ?? 'Not specified'}
                            />
                            <CampaignOverviewField
                                label="Assessment language"
                                value={campaign.language}
                            />
                            <CampaignOverviewField
                                label="Threshold score"
                                value={numberFormatter.format(
                                    campaign.threshold_score,
                                )}
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
                                                        className="max-w-full break-words whitespace-normal"
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
                                    campaign.job_description ?? 'Not specified'
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
            <p className="min-w-0 flex-1 text-sm break-words whitespace-pre-line text-muted-foreground">
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
    const { locale, timeZone } = usePage<SharedData>().props;
    const dateTimeFormatter = new Intl.DateTimeFormat(normalizeLocale(locale), {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone,
    });

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="default" variant="outline">
                    <Share2 aria-hidden="true" data-icon="inline-start" />
                    Share…
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Candidate Invitations</DialogTitle>
                    <DialogDescription>
                        Assign candidates to this campaign and share their
                        invite links before they can open the exam.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    action={admin.campaigns.invitations.store.url(campaign.id)}
                    method="post"
                    options={{ preserveScroll: true }}
                    onError={focusFirstError}
                >
                    {({ errors, isDirty, processing }) => (
                        <FieldGroup>
                            <UnsavedChangesGuard
                                active={isDirty && !processing}
                            />
                            <Field
                                data-invalid={
                                    Boolean(errors.email) || undefined
                                }
                            >
                                <FieldLabel htmlFor="invitation-email">
                                    Candidate email
                                </FieldLabel>
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                    <Input
                                        className="flex-1"
                                        id="invitation-email"
                                        name="email"
                                        type="email"
                                        inputMode="email"
                                        autoComplete="email"
                                        spellCheck={false}
                                        placeholder="Example: candidate@example.com…"
                                        required
                                        aria-invalid={
                                            Boolean(errors.email) || undefined
                                        }
                                    />
                                    <Button disabled={processing} type="submit">
                                        {processing && <Spinner />}
                                        <Mail
                                            aria-hidden="true"
                                            data-icon="inline-start"
                                        />
                                        Send Invitation
                                    </Button>
                                </div>
                                <FieldError
                                    errors={[
                                        {
                                            message: errors.email,
                                        },
                                    ]}
                                />
                            </Field>
                            <input type="hidden" name="send_email" value="1" />
                        </FieldGroup>
                    )}
                </Form>

                {latestInviteUrl ? (
                    <p
                        aria-live="polite"
                        className="rounded-md bg-background px-3 py-2 text-sm break-all"
                    >
                        Latest invite link: {latestInviteUrl}
                    </p>
                ) : null}

                <div className="overflow-x-auto">
                    <Table className="min-w-[720px]">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Email</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Sent</TableHead>
                                <TableHead>Accepted</TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {invitations.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        className="text-muted-foreground"
                                        colSpan={5}
                                    >
                                        No invitations yet.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                invitations.map((invitation) => (
                                    <TableRow
                                        key={invitation.id}
                                        className="[contain-intrinsic-size:auto_44px] [content-visibility:auto]"
                                    >
                                        <TableCell className="max-w-64 break-all">
                                            {invitation.email}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary">
                                                {invitation.status_label}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {invitation.sent_at ? (
                                                <time
                                                    className="tabular-nums"
                                                    dateTime={
                                                        invitation.sent_at
                                                    }
                                                >
                                                    {dateTimeFormatter.format(
                                                        new Date(
                                                            invitation.sent_at,
                                                        ),
                                                    )}
                                                </time>
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {invitation.accepted_at ? (
                                                <time
                                                    className="tabular-nums"
                                                    dateTime={
                                                        invitation.accepted_at
                                                    }
                                                >
                                                    {dateTimeFormatter.format(
                                                        new Date(
                                                            invitation.accepted_at,
                                                        ),
                                                    )}
                                                </time>
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-2">
                                                {invitation.can_resend ? (
                                                    <Form
                                                        {...admin.campaigns.invitations.resend.form(
                                                            {
                                                                campaign:
                                                                    campaign.id,
                                                                invitation:
                                                                    invitation.id,
                                                            },
                                                        )}
                                                        options={{
                                                            preserveScroll: true,
                                                        }}
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                disabled={
                                                                    processing
                                                                }
                                                                size="default"
                                                                type="submit"
                                                                variant="outline"
                                                            >
                                                                {processing && (
                                                                    <Spinner />
                                                                )}
                                                                Resend
                                                            </Button>
                                                        )}
                                                    </Form>
                                                ) : null}
                                                {invitation.can_revoke ? (
                                                    <AlertDialog>
                                                        <AlertDialogTrigger
                                                            asChild
                                                        >
                                                            <Button
                                                                size="default"
                                                                type="button"
                                                                variant="destructive"
                                                            >
                                                                Revoke…
                                                            </Button>
                                                        </AlertDialogTrigger>
                                                        <AlertDialogContent>
                                                            <AlertDialogHeader>
                                                                <AlertDialogTitle>
                                                                    Revoke
                                                                    Invitation?
                                                                </AlertDialogTitle>
                                                                <AlertDialogDescription>
                                                                    Revoke the
                                                                    invitation
                                                                    for{' '}
                                                                    <span className="break-all">
                                                                        {
                                                                            invitation.email
                                                                        }
                                                                    </span>
                                                                    ? The
                                                                    candidate
                                                                    will no
                                                                    longer be
                                                                    able to use
                                                                    this link.
                                                                </AlertDialogDescription>
                                                            </AlertDialogHeader>
                                                            <Form
                                                                {...admin.campaigns.invitations.destroy.form(
                                                                    {
                                                                        campaign:
                                                                            campaign.id,
                                                                        invitation:
                                                                            invitation.id,
                                                                    },
                                                                )}
                                                                options={{
                                                                    preserveScroll: true,
                                                                }}
                                                            >
                                                                {({
                                                                    processing,
                                                                }) => (
                                                                    <AlertDialogFooter>
                                                                        <AlertDialogCancel>
                                                                            Cancel
                                                                        </AlertDialogCancel>
                                                                        <Button
                                                                            disabled={
                                                                                processing
                                                                            }
                                                                            type="submit"
                                                                            variant="destructive"
                                                                        >
                                                                            {processing && (
                                                                                <Spinner />
                                                                            )}
                                                                            Revoke
                                                                            Invitation
                                                                        </Button>
                                                                    </AlertDialogFooter>
                                                                )}
                                                            </Form>
                                                        </AlertDialogContent>
                                                    </AlertDialog>
                                                ) : null}
                                            </div>
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
        if (campaign.definition_frozen) {
            return null;
        }

        return (
            <Form
                {...CampaignStatusController.draft.form(campaign.id)}
                options={{ preserveScroll: true }}
            >
                {({ processing }) => (
                    <Button
                        size="default"
                        variant="outline"
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        Move to Draft
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
                {({ processing, errors }) => (
                    <div className="flex flex-col gap-1">
                        <Button
                            size="default"
                            variant="outline"
                            disabled={processing || !campaign.can_archive}
                        >
                            {processing && <Spinner />}
                            Archive
                        </Button>
                        <InputError message={errors.campaign} />
                    </div>
                )}
            </Form>
        );
    }

    if (campaign.definition_frozen) {
        return null;
    }

    return (
        <Form
            {...CampaignController.publish.form(campaign.id)}
            options={{ preserveScroll: true }}
        >
            {({ errors, processing }) => (
                <div className="flex flex-col gap-1">
                    <Button
                        size="default"
                        disabled={processing || !campaign.can_publish}
                    >
                        {processing && <Spinner />}
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
                    <Button size="icon" variant="ghost">
                        <MoreHorizontal
                            aria-hidden="true"
                            data-icon="inline-start"
                        />
                        <span className="sr-only">Open campaign actions</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" className="w-48">
                    <DropdownMenuGroup>
                        {!campaign.definition_frozen ? (
                            <DropdownMenuItem
                                onSelect={(event) => {
                                    event.preventDefault();
                                    setEditOpen(true);
                                }}
                            >
                                Edit Campaign…
                            </DropdownMenuItem>
                        ) : null}
                        {campaign.can_clone ? (
                            <DropdownMenuItem
                                onSelect={(event) => {
                                    event.preventDefault();
                                    router.post(
                                        CampaignCloneController.store.url(
                                            campaign.id,
                                        ),
                                    );
                                }}
                            >
                                Clone as New Draft
                            </DropdownMenuItem>
                        ) : null}
                    </DropdownMenuGroup>
                    {!campaign.definition_frozen ? (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={(event) => {
                                    event.preventDefault();
                                    onDelete();
                                }}
                            >
                                Delete Campaign…
                            </DropdownMenuItem>
                        </>
                    ) : null}
                </DropdownMenuContent>
            </DropdownMenu>

            {!campaign.definition_frozen ? (
                <Sheet open={editOpen} onOpenChange={setEditOpen}>
                    <SheetContent className="bg-card text-card-foreground data-[side=right]:sm:max-w-2xl">
                        <SheetHeader>
                            <SheetTitle>Edit Campaign</SheetTitle>
                            <SheetDescription>
                                Update role context, required skills, and
                                scoring threshold.
                            </SheetDescription>
                        </SheetHeader>
                        <CampaignForm
                            action={CampaignController.update.form.patch(
                                campaign.id,
                            )}
                            submitLabel="Save Changes"
                            campaign={campaign}
                            onSuccess={() => setEditOpen(false)}
                            onCancel={() => setEditOpen(false)}
                            className="flex-1 overflow-hidden"
                            bodyClassName="flex-1 overflow-y-auto px-4"
                            footerClassName="mt-auto border-t bg-background p-4"
                        />
                    </SheetContent>
                </Sheet>
            ) : null}
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
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Campaign?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This will permanently delete “{campaign.title}”. This
                        action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <Form
                    {...CampaignController.destroy.form.delete(campaign.id)}
                    onSuccess={() => onOpenChange(false)}
                    className="flex flex-col gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <InputError message={errors.campaign} />

                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    Delete Campaign
                                </Button>
                            </AlertDialogFooter>
                        </>
                    )}
                </Form>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function QuestionActionsDropdown({
    campaignId,
    question,
    sections,
    questionTypes,
}: {
    campaignId: number;
    question: CampaignQuestion;
    sections: CampaignSection[];
    questionTypes: QuestionTypeOption[];
}) {
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        size="icon"
                        variant="ghost"
                        aria-label="Open question actions"
                    >
                        <MoreHorizontal
                            aria-hidden="true"
                            data-icon="inline-start"
                        />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-40">
                    <DropdownMenuGroup>
                        <DropdownMenuItem
                            onSelect={(event) => {
                                event.preventDefault();
                                setEditOpen(true);
                            }}
                        >
                            Edit Question…
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
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Question?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This will permanently delete this question. This action
                        cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>

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

                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    Delete Question
                                </Button>
                            </AlertDialogFooter>
                        </>
                    )}
                </Form>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function EditCampaignQuestionSheet({
    campaignId,
    question,
    sections,
    questionTypes,
    open,
    onOpenChange,
}: {
    campaignId: number;
    question: CampaignQuestion;
    sections: CampaignSection[];
    questionTypes: QuestionTypeOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="bg-card text-card-foreground data-[side=right]:sm:max-w-2xl">
                <SheetHeader>
                    <SheetTitle>Edit Question</SheetTitle>
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
                    onSuccess={() => onOpenChange(false)}
                    onError={focusFirstError}
                    className="flex flex-1 flex-col overflow-hidden"
                >
                    {({ errors, isDirty, processing }) => (
                        <>
                            <UnsavedChangesGuard
                                active={isDirty && !processing}
                            />
                            <FieldGroup className="flex-1 overflow-y-auto px-4">
                                <Field
                                    data-invalid={Boolean(
                                        errors.campaign_section_id,
                                    )}
                                >
                                    <FieldLabel
                                        htmlFor={`edit-section-${question.id}`}
                                    >
                                        Section
                                    </FieldLabel>
                                    <Select
                                        name="campaign_section_id"
                                        defaultValue={question.campaign_section_id.toString()}
                                    >
                                        <SelectTrigger
                                            id={`edit-section-${question.id}`}
                                            className="w-full"
                                            aria-invalid={Boolean(
                                                errors.campaign_section_id,
                                            )}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {sections.map((section) => (
                                                    <SelectItem
                                                        key={section.id}
                                                        value={section.id.toString()}
                                                    >
                                                        {section.title}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                    <FieldError>
                                        {errors.campaign_section_id}
                                    </FieldError>
                                </Field>

                                <Field data-invalid={Boolean(errors.type)}>
                                    <FieldLabel
                                        htmlFor={`edit-type-${question.id}`}
                                    >
                                        Type
                                    </FieldLabel>
                                    <Select
                                        name="type"
                                        defaultValue={question.type}
                                    >
                                        <SelectTrigger
                                            id={`edit-type-${question.id}`}
                                            className="w-full"
                                            aria-invalid={Boolean(errors.type)}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {questionTypes.map((type) => (
                                                    <SelectItem
                                                        key={type.value}
                                                        value={type.value}
                                                    >
                                                        {type.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                    <FieldError>{errors.type}</FieldError>
                                </Field>

                                <Field
                                    data-invalid={Boolean(errors.difficulty)}
                                >
                                    <FieldLabel
                                        htmlFor={`edit-difficulty-${question.id}`}
                                    >
                                        Difficulty
                                    </FieldLabel>
                                    <Select
                                        name="difficulty"
                                        defaultValue={question.difficulty}
                                    >
                                        <SelectTrigger
                                            id={`edit-difficulty-${question.id}`}
                                            className="w-full"
                                            aria-invalid={Boolean(
                                                errors.difficulty,
                                            )}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
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
                                    <FieldError>{errors.difficulty}</FieldError>
                                </Field>

                                <Field data-invalid={Boolean(errors.prompt)}>
                                    <FieldLabel
                                        htmlFor={`edit-prompt-${question.id}`}
                                    >
                                        Question
                                    </FieldLabel>
                                    <Textarea
                                        id={`edit-prompt-${question.id}`}
                                        name="prompt"
                                        autoComplete="off"
                                        rows={4}
                                        defaultValue={question.prompt}
                                        required
                                        className="min-h-28"
                                        aria-invalid={Boolean(errors.prompt)}
                                    />
                                    <FieldError>{errors.prompt}</FieldError>
                                </Field>

                                <Field
                                    data-invalid={Boolean(
                                        errors.expected_rubric,
                                    )}
                                >
                                    <FieldLabel
                                        htmlFor={`edit-rubric-${question.id}`}
                                    >
                                        Expected answer / scoring guide
                                    </FieldLabel>
                                    <Textarea
                                        id={`edit-rubric-${question.id}`}
                                        name="expected_rubric"
                                        autoComplete="off"
                                        rows={4}
                                        defaultValue={
                                            question.expected_rubric ?? ''
                                        }
                                        required
                                        className="min-h-28"
                                        aria-invalid={Boolean(
                                            errors.expected_rubric,
                                        )}
                                    />
                                    <FieldError>
                                        {errors.expected_rubric}
                                    </FieldError>
                                </Field>

                                <PointsField
                                    id={`edit-points-${question.id}`}
                                    initialValue={question.points}
                                    error={errors.points}
                                />

                                <input
                                    type="hidden"
                                    name="ai_generated"
                                    value={question.ai_generated ? '1' : '0'}
                                />
                            </FieldGroup>

                            <SheetFooter className="flex-row items-center justify-between gap-3 border-t bg-background sm:flex-row">
                                <SheetClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="default"
                                    >
                                        Cancel
                                    </Button>
                                </SheetClose>
                                <Button
                                    type="submit"
                                    size="default"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Save Question
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
