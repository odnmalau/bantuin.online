import { Form, Head, Link } from '@inertiajs/react';
import { Eye, Plus, SquarePen, Trash2 } from 'lucide-react';
import CampaignController from '@/actions/App/Http/Controllers/Admin/CampaignController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import admin from '@/routes/admin';

type CampaignRow = {
    id: number;
    title: string;
    role_title: string;
    seniority: string | null;
    threshold_score: number;
    status: string;
    status_label: string;
    sections_count: number;
    questions_count: number;
    assessments_count: number;
    created_by: string | null;
    created_at: string;
};

type Props = {
    campaigns: CampaignRow[];
};

export default function AdminCampaignsIndex({ campaigns }: Props) {
    return (
        <>
            <Head title="Campaigns" />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Campaigns"
                        description="Manage role-based hiring assessment campaigns."
                    />
                    <Button asChild>
                        <Link href={admin.campaigns.create()}>
                            <Plus />
                            New campaign
                        </Link>
                    </Button>
                </div>

                {campaigns.length === 0 ? (
                    <div className="rounded-lg border border-sidebar-border/70 p-8 text-center dark:border-sidebar-border">
                        <h2 className="text-base font-medium">
                            No campaigns yet
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Create a campaign before generating or assigning
                            assessment questions.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[960px] text-sm">
                                <thead className="border-b bg-muted/40 text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">
                                            Campaign
                                        </th>
                                        <th className="w-28 px-4 py-3 font-medium">
                                            Threshold
                                        </th>
                                        <th className="w-32 px-4 py-3 font-medium">
                                            Status
                                        </th>
                                        <th className="w-28 px-4 py-3 font-medium">
                                            Sections
                                        </th>
                                        <th className="w-28 px-4 py-3 font-medium">
                                            Questions
                                        </th>
                                        <th className="w-32 px-4 py-3 font-medium">
                                            Submissions
                                        </th>
                                        <th className="w-40 px-4 py-3 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {campaigns.map((campaign) => (
                                        <tr key={campaign.id}>
                                            <td className="px-4 py-4 align-top">
                                                <div className="space-y-1">
                                                    <p className="font-medium">
                                                        {campaign.title}
                                                    </p>
                                                    <p className="text-muted-foreground">
                                                        {campaign.role_title}
                                                        {campaign.seniority
                                                            ? `, ${campaign.seniority}`
                                                            : ''}
                                                    </p>
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 align-top font-medium">
                                                {campaign.threshold_score}
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <Badge
                                                    variant={
                                                        campaign.status ===
                                                        'active'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {campaign.status_label}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-4 align-top text-muted-foreground">
                                                {campaign.sections_count}
                                            </td>
                                            <td className="px-4 py-4 align-top text-muted-foreground">
                                                {campaign.questions_count}
                                            </td>
                                            <td className="px-4 py-4 align-top text-muted-foreground">
                                                {campaign.assessments_count}
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        size="icon"
                                                        variant="outline"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={admin.campaigns.show(
                                                                campaign.id,
                                                            )}
                                                            aria-label="View campaign"
                                                        >
                                                            <Eye />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        size="icon"
                                                        variant="outline"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={admin.campaigns.edit(
                                                                campaign.id,
                                                            )}
                                                            aria-label="Edit campaign"
                                                        >
                                                            <SquarePen />
                                                        </Link>
                                                    </Button>
                                                    <Form
                                                        {...CampaignController.destroy.form.delete(
                                                            campaign.id,
                                                        )}
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                size="icon"
                                                                variant="outline"
                                                                disabled={
                                                                    processing
                                                                }
                                                                aria-label="Delete campaign"
                                                            >
                                                                <Trash2 />
                                                            </Button>
                                                        )}
                                                    </Form>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </>
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
