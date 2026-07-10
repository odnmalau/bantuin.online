import { router } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    ClipboardList,
    Trophy,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardAction,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import admin from '@/routes/admin';

export type RecentCampaign = {
    id: number;
    title: string;
    role_title: string;
    seniority: string;
    status: string;
    status_label: string;
    pending_approval_count: number;
    needs_manual_review_count: number;
    ranked_count: number;
    updated_at: string | null;
};

function CampaignMetric({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: number;
    icon: typeof Trophy;
}) {
    return (
        <div className="flex flex-col gap-1 rounded-lg border bg-muted/40 px-3 py-2">
            <div className="flex items-center gap-1.5 text-muted-foreground">
                <Icon className="size-3.5" />
                <span className="text-xs">{label}</span>
            </div>
            <p className="text-lg font-semibold tabular-nums">{value}</p>
        </div>
    );
}

function RecentCampaignCard({ campaign }: { campaign: RecentCampaign }) {
    return (
        <Card
            size="sm"
            role="link"
            tabIndex={0}
            className="cursor-pointer"
            onClick={() => router.visit(admin.campaigns.show.url(campaign.id))}
            onKeyDown={(event) => {
                if (event.currentTarget !== event.target) {
                    return;
                }

                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    router.visit(admin.campaigns.show.url(campaign.id));
                }
            }}
        >
            <CardHeader>
                <CardTitle>{campaign.title}</CardTitle>
                <CardAction>
                    <Badge
                        variant={
                            campaign.status === 'active'
                                ? 'default'
                                : 'secondary'
                        }
                    >
                        {campaign.status_label}
                    </Badge>
                </CardAction>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <div className="flex flex-col gap-1">
                    <p className="text-sm font-medium">{campaign.role_title}</p>
                    <p className="text-sm text-muted-foreground">
                        {campaign.seniority || 'No seniority specified'}
                    </p>
                </div>

                <div className="grid grid-cols-2 gap-2">
                    <CampaignMetric
                        label="Pending"
                        value={campaign.pending_approval_count}
                        icon={ClipboardList}
                    />
                    <CampaignMetric
                        label="Ranked"
                        value={campaign.ranked_count}
                        icon={Trophy}
                    />
                </div>
            </CardContent>
        </Card>
    );
}

export function RecentCampaignsSection({
    campaigns,
}: {
    campaigns: RecentCampaign[];
}) {
    if (campaigns.length === 0) {
        return (
            <Empty className="border border-dashed">
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <BriefcaseBusiness />
                    </EmptyMedia>
                    <EmptyTitle>No campaigns yet</EmptyTitle>
                    <EmptyDescription>
                        Create a campaign to start collecting assessments and
                        rankings.
                    </EmptyDescription>
                </EmptyHeader>
            </Empty>
        );
    }

    return (
        <section className="flex flex-col gap-3">
            <div className="flex flex-col gap-1">
                <h2 className="text-base font-medium">Recent campaigns</h2>
                <p className="text-sm text-muted-foreground">
                    Campaigns that need attention first, then the latest
                    activity.
                </p>
            </div>
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {campaigns.map((campaign) => (
                    <RecentCampaignCard
                        key={campaign.id}
                        campaign={campaign}
                    />
                ))}
            </div>
        </section>
    );
}
