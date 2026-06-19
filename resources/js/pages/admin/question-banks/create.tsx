import { Head } from '@inertiajs/react';
import { store } from '@/actions/App/Http/Controllers/Admin/QuestionBankController';
import QuestionBankForm from '@/components/admin/question-bank-form';
import Heading from '@/components/heading';
import admin from '@/routes/admin';

type DifficultyOption = {
    value: string;
    label: string;
};

type Props = {
    difficultyOptions: DifficultyOption[];
};

export default function AdminQuestionBanksCreate({ difficultyOptions }: Props) {
    return (
        <>
            <Head title="New Question Library" />

            <div className="space-y-6 p-4">
                <Heading
                    title="New Question Library"
                    description="Create a reusable bank for manual or AI-generated questions."
                />

                <div className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                    <QuestionBankForm
                        action={store.form()}
                        submitLabel="Create library"
                        difficultyOptions={difficultyOptions}
                    />
                </div>
            </div>
        </>
    );
}

AdminQuestionBanksCreate.layout = {
    breadcrumbs: [
        {
            title: 'Question Libraries',
            href: admin.questionBanks.index(),
        },
        {
            title: 'Create',
            href: admin.questionBanks.create(),
        },
    ],
};
