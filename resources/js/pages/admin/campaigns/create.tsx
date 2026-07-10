import { Head } from '@inertiajs/react';
import CampaignController from '@/actions/App/Http/Controllers/Admin/CampaignController';
import CampaignForm from '@/components/admin/campaign-form';
import admin from '@/routes/admin';

export default function AdminCampaignsCreate() {
    return (
        <>
            <Head title="Create Campaign" />

            <div className="max-w-4xl space-y-6 p-4">
                <CampaignForm
                    action={CampaignController.store.form()}
                    submitLabel="Create campaign"
                />
            </div>
        </>
    );
}

AdminCampaignsCreate.layout = {
    breadcrumbs: [
        {
            title: 'Campaigns',
            href: admin.campaigns.index(),
        },
        {
            title: 'Create',
            href: admin.campaigns.create(),
        },
    ],
};
