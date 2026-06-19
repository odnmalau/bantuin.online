import { Head } from '@inertiajs/react';
import CampaignController from '@/actions/App/Http/Controllers/Admin/CampaignController';
import CampaignForm from '@/components/admin/campaign-form';
import type { CampaignFormValues } from '@/components/admin/campaign-form';
import Heading from '@/components/heading';
import admin from '@/routes/admin';

type Option = {
    value: string;
    label: string;
};

type Props = {
    campaign: CampaignFormValues & {
        id: number;
    };
    statusOptions: Option[];
};

export default function AdminCampaignsEdit({ campaign, statusOptions }: Props) {
    return (
        <>
            <Head title="Edit Campaign" />

            <div className="max-w-4xl space-y-6 p-4">
                <Heading
                    title="Edit Campaign"
                    description="Update role context, status, and scoring threshold."
                />

                <CampaignForm
                    action={CampaignController.update.form.patch(campaign.id)}
                    submitLabel="Save changes"
                    campaign={campaign}
                    statusOptions={statusOptions}
                />
            </div>
        </>
    );
}

AdminCampaignsEdit.layout = {
    breadcrumbs: [
        {
            title: 'Campaigns',
            href: admin.campaigns.index(),
        },
        {
            title: 'Edit',
            href: admin.campaigns.edit(0),
        },
    ],
};
