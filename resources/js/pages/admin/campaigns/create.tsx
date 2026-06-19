import { Head } from '@inertiajs/react';
import CampaignController from '@/actions/App/Http/Controllers/Admin/CampaignController';
import CampaignForm from '@/components/admin/campaign-form';
import type { CampaignRankingWeights } from '@/components/admin/campaign-form';
import Heading from '@/components/heading';
import admin from '@/routes/admin';

type Option = {
    value: string;
    label: string;
};

type Props = {
    statusOptions: Option[];
    defaultRankingWeights: CampaignRankingWeights;
};

export default function AdminCampaignsCreate({
    statusOptions,
    defaultRankingWeights,
}: Props) {
    return (
        <>
            <Head title="Create Campaign" />

            <div className="max-w-4xl space-y-6 p-4">
                <Heading
                    title="Create Campaign"
                    description="Define the role context, required skills, ranking weights, and passing threshold."
                />

                <CampaignForm
                    action={CampaignController.store.form()}
                    submitLabel="Create campaign"
                    statusOptions={statusOptions}
                    defaultRankingWeights={defaultRankingWeights}
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
