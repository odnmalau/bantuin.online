import {
    Deferred,
    Form,
    Head,
    Link,
    router,
    useForm,
    usePage,
} from '@inertiajs/react';
import { MoreHorizontal, Plus, SlidersHorizontal } from 'lucide-react';
import type { FormEvent } from 'react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import CampaignCloneController from '@/actions/App/Http/Controllers/Admin/CampaignCloneController';
import CampaignController from '@/actions/App/Http/Controllers/Admin/CampaignController';
import CampaignForm from '@/components/admin/campaign-form';
import InputError from '@/components/input-error';
import { PaginationControls } from '@/components/pagination-controls';
import type { Paginated } from '@/components/pagination-controls';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import admin from '@/routes/admin';
import type { SharedData } from '@/types';

type CampaignRow = {
    id: number;
    title: string;
    role_title: string;
    seniority: string | null;
    job_description: string | null;
    required_skills: string[];
    language: string | null;
    threshold_score: number;
    sections_count: number;
    questions_count: number;
    assessments_count: number;
    definition_frozen: boolean;
    created_by: string | null;
    created_at: string;
};

type CampaignFilters = {
    search: string;
    status: string;
};

type SelectOption = {
    value: string;
    label: string;
};

type Props = {
    campaigns?: Paginated<CampaignRow>;
    filters: CampaignFilters;
    statusOptions: SelectOption[];
};

function CampaignMetric({
    label,
    value,
}: {
    label: string;
    value: number | string;
}) {
    return (
        <div className="rounded-md border border-sidebar-border/70 p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-base font-medium">{value}</p>
        </div>
    );
}

function CampaignsGridSkeleton() {
    return (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {Array.from({ length: 6 }).map((_, index) => (
                <Card key={index} size="sm">
                    <CardHeader>
                        <Skeleton className="h-5 w-40" />
                        <Skeleton className="h-6 w-16" />
                    </CardHeader>
                    <CardContent className="flex flex-1 flex-col gap-4">
                        <div className="flex flex-col gap-2">
                            <Skeleton className="h-4 w-32" />
                            <Skeleton className="h-4 w-24" />
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            {Array.from({ length: 4 }).map(
                                (__, metricIndex) => (
                                    <div
                                        key={metricIndex}
                                        className="rounded-md border border-sidebar-border/70 p-3"
                                    >
                                        <Skeleton className="h-3 w-16" />
                                        <Skeleton className="mt-2 h-5 w-10" />
                                    </div>
                                ),
                            )}
                        </div>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

export default function AdminCampaignsIndex({
    campaigns,
    filters,
    statusOptions,
}: Props) {
    const { data, setData, get, processing } = useForm<CampaignFilters>({
        search: filters.search ?? '',
        status: filters.status ?? 'all',
    });
    const [campaignPendingDeletion, setCampaignPendingDeletion] =
        useState<CampaignRow | null>(null);
    const [campaignPendingEdit, setCampaignPendingEdit] =
        useState<CampaignRow | null>(null);
    const { auth } = usePage<SharedData>().props;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        get(admin.campaigns.index.url(), {
            preserveScroll: true,
            preserveState: true,
        });
    }

    function applyFilters(nextFilters: CampaignFilters) {
        setData(nextFilters);

        router.get(admin.campaigns.index.url(), nextFilters, {
            preserveScroll: true,
            preserveState: true,
        });
    }

    return (
        <>
            <Head title="Campaigns" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-2 lg:flex-row">
                        <form
                            onSubmit={submit}
                            className="flex flex-1 flex-col gap-2 sm:flex-row"
                        >
                            <Input
                                type="search"
                                value={data.search}
                                onChange={(event) =>
                                    setData('search', event.target.value)
                                }
                                placeholder="Search campaigns by title, role, or seniority"
                                className="flex-1"
                            />
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="outline"
                                        disabled={processing}
                                        aria-label="Open filters"
                                    >
                                        <SlidersHorizontal data-icon="inline-start" />
                                        <span className="sr-only">
                                            Open filters
                                        </span>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    className="w-56"
                                >
                                    <DropdownMenuGroup>
                                        <DropdownMenuLabel>
                                            Status
                                        </DropdownMenuLabel>
                                        <DropdownMenuRadioGroup
                                            value={data.status}
                                            onValueChange={(status) =>
                                                applyFilters({
                                                    ...data,
                                                    status,
                                                })
                                            }
                                        >
                                            <DropdownMenuRadioItem value="all">
                                                All statuses
                                            </DropdownMenuRadioItem>
                                            {statusOptions.map((option) => (
                                                <DropdownMenuRadioItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </DropdownMenuRadioItem>
                                            ))}
                                        </DropdownMenuRadioGroup>
                                    </DropdownMenuGroup>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </form>
                        <CreateCampaignSheet>
                            <Button
                                disabled={!auth.capabilities.manageCampaigns}
                            >
                                <Plus data-icon="inline-start" />
                                New campaign
                            </Button>
                        </CreateCampaignSheet>
                    </div>
                </div>

                <Deferred data="campaigns" fallback={<CampaignsGridSkeleton />}>
                    {campaigns !== undefined && campaigns.data.length === 0 ? (
                        <div className="rounded-lg border border-sidebar-border/70 p-8 text-center dark:border-sidebar-border">
                            <h2 className="text-base font-medium">
                                No campaigns yet
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Create a campaign before generating or assigning
                                assessment questions.
                            </p>
                        </div>
                    ) : campaigns !== undefined ? (
                        <div className="flex flex-col gap-4">
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {campaigns.data.map((campaign) => (
                                    <Card
                                        key={campaign.id}
                                        size="sm"
                                        className="relative"
                                    >
                                        <Link
                                            href={admin.campaigns.show(
                                                campaign.id,
                                            )}
                                            prefetch
                                            aria-label={`View ${campaign.title}`}
                                            className="absolute inset-0 z-10 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                        >
                                            <span className="sr-only">
                                                View {campaign.title}
                                            </span>
                                        </Link>
                                        <CardHeader className="pointer-events-none relative z-20">
                                            <CardTitle>
                                                {campaign.title}
                                            </CardTitle>
                                            <CardAction className="pointer-events-auto relative z-30 flex items-center gap-2">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            disabled={
                                                                !auth
                                                                    .capabilities
                                                                    .manageCampaigns
                                                            }
                                                            aria-label="Open campaign actions"
                                                        >
                                                            <MoreHorizontal data-icon="inline-start" />
                                                            <span className="sr-only">
                                                                Open campaign
                                                                actions
                                                            </span>
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent
                                                        align="end"
                                                        className="w-48"
                                                    >
                                                        <DropdownMenuGroup>
                                                            {!campaign.definition_frozen ? (
                                                                <DropdownMenuItem
                                                                    onSelect={(
                                                                        event,
                                                                    ) => {
                                                                        event.preventDefault();
                                                                        setCampaignPendingEdit(
                                                                            campaign,
                                                                        );
                                                                    }}
                                                                >
                                                                    Edit
                                                                    campaign
                                                                </DropdownMenuItem>
                                                            ) : null}
                                                            <DropdownMenuItem
                                                                onSelect={(
                                                                    event,
                                                                ) => {
                                                                    event.preventDefault();
                                                                    router.post(
                                                                        CampaignCloneController.store.url(
                                                                            campaign.id,
                                                                        ),
                                                                    );
                                                                }}
                                                            >
                                                                Clone as new
                                                                draft
                                                            </DropdownMenuItem>
                                                        </DropdownMenuGroup>
                                                        {!campaign.definition_frozen ? (
                                                            <>
                                                                <DropdownMenuSeparator />
                                                                <DropdownMenuItem
                                                                    variant="destructive"
                                                                    onSelect={(
                                                                        event,
                                                                    ) => {
                                                                        event.preventDefault();
                                                                        setCampaignPendingDeletion(
                                                                            campaign,
                                                                        );
                                                                    }}
                                                                >
                                                                    Delete
                                                                    campaign
                                                                </DropdownMenuItem>
                                                            </>
                                                        ) : null}
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </CardAction>
                                        </CardHeader>
                                        <CardContent className="pointer-events-none relative z-20 flex flex-1 flex-col gap-4">
                                            <div className="flex flex-col gap-1">
                                                <p className="text-sm font-medium">
                                                    {campaign.role_title}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {campaign.seniority ||
                                                        'No seniority specified'}
                                                </p>
                                            </div>
                                            <div className="grid grid-cols-2 gap-2">
                                                <CampaignMetric
                                                    label="Threshold"
                                                    value={
                                                        campaign.threshold_score
                                                    }
                                                />
                                                <CampaignMetric
                                                    label="Submissions"
                                                    value={
                                                        campaign.assessments_count
                                                    }
                                                />
                                                <CampaignMetric
                                                    label="Sections"
                                                    value={
                                                        campaign.sections_count
                                                    }
                                                />
                                                <CampaignMetric
                                                    label="Questions"
                                                    value={
                                                        campaign.questions_count
                                                    }
                                                />
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                            <PaginationControls
                                paginator={campaigns}
                                only={['campaigns']}
                            />
                        </div>
                    ) : null}
                </Deferred>

                <EditCampaignSheet
                    campaign={campaignPendingEdit}
                    onOpenChange={(open) => {
                        if (!open) {
                            setCampaignPendingEdit(null);
                        }
                    }}
                />

                <DeleteCampaignDialog
                    campaign={campaignPendingDeletion}
                    onOpenChange={(open) => {
                        if (!open) {
                            setCampaignPendingDeletion(null);
                        }
                    }}
                />
            </div>
        </>
    );
}

function CreateCampaignSheet({ children }: { children: ReactNode }) {
    const [open, setOpen] = useState(false);

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>{children}</SheetTrigger>
            <SheetContent className="bg-card text-card-foreground data-[side=right]:sm:max-w-2xl">
                <SheetHeader>
                    <SheetTitle>Create campaign</SheetTitle>
                    <SheetDescription>
                        Define the role context, required skills, and passing
                        threshold.
                    </SheetDescription>
                </SheetHeader>
                <CampaignForm
                    action={CampaignController.store.form()}
                    submitLabel="Create campaign"
                    onSuccess={() => setOpen(false)}
                    onCancel={() => setOpen(false)}
                    className="flex-1 overflow-hidden"
                    bodyClassName="flex-1 overflow-y-auto px-4"
                    footerClassName="mt-auto border-t bg-background p-4"
                />
            </SheetContent>
        </Sheet>
    );
}

function EditCampaignSheet({
    campaign,
    onOpenChange,
}: {
    campaign: CampaignRow | null;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Sheet open={campaign !== null} onOpenChange={onOpenChange}>
            <SheetContent className="bg-card text-card-foreground data-[side=right]:sm:max-w-2xl">
                <SheetHeader>
                    <SheetTitle>Edit campaign</SheetTitle>
                    <SheetDescription>
                        Update role context, required skills, and scoring
                        threshold.
                    </SheetDescription>
                </SheetHeader>
                {campaign !== null ? (
                    <CampaignForm
                        action={CampaignController.update.form.patch(
                            campaign.id,
                        )}
                        submitLabel="Save changes"
                        campaign={campaign}
                        onSuccess={() => onOpenChange(false)}
                        onCancel={() => onOpenChange(false)}
                        className="flex-1 overflow-hidden"
                        bodyClassName="flex-1 overflow-y-auto px-4"
                        footerClassName="mt-auto border-t bg-background p-4"
                    />
                ) : null}
            </SheetContent>
        </Sheet>
    );
}

function DeleteCampaignDialog({
    campaign,
    onOpenChange,
}: {
    campaign: CampaignRow | null;
    onOpenChange: (open: boolean) => void;
}) {
    const canDelete = (campaign?.assessments_count ?? 0) === 0;

    return (
        <Dialog open={campaign !== null} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>Delete campaign?</DialogTitle>
                <DialogDescription>
                    {campaign === null
                        ? 'Confirm campaign deletion.'
                        : `This will permanently delete "${campaign.title}". This action cannot be undone.`}
                </DialogDescription>

                {!canDelete && (
                    <p className="text-sm text-destructive">
                        Campaigns with invitations, exam attempts, or
                        assessments cannot be deleted.
                    </p>
                )}

                {campaign !== null && (
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
                                        disabled={processing || !canDelete}
                                    >
                                        Delete campaign
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}

AdminCampaignsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Campaigns',
            href: admin.campaigns.index(),
        },
    ],
};
