import { Head } from '@inertiajs/react';
import CampaignController from '@/actions/App/Http/Controllers/Admin/CampaignController';
import CampaignForm from '@/components/admin/campaign-form';
import type { CampaignFormValues } from '@/components/admin/campaign-form';
import admin from '@/routes/admin';

type Props = {
    campaign: CampaignFormValues & {
        id: number;
    };
};

export default function AdminCampaignsEdit({ campaign }: Props) {
    return (
        <>
            <Head title="Edit Campaign" />

            <div className="max-w-4xl space-y-6 p-4">
                <CampaignForm
                    action={CampaignController.update.form.patch(campaign.id)}
                    submitLabel="Save changes"
                    campaign={campaign}
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
