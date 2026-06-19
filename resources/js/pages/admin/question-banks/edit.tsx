import { Head } from '@inertiajs/react';
import { update } from '@/actions/App/Http/Controllers/Admin/QuestionBankController';
import QuestionBankForm from '@/components/admin/question-bank-form';
import type { QuestionBankFormValues } from '@/components/admin/question-bank-form';
import Heading from '@/components/heading';
import admin from '@/routes/admin';

type DifficultyOption = {
    value: string;
    label: string;
};

type Props = {
    questionBank: QuestionBankFormValues & { id: number };
    difficultyOptions: DifficultyOption[];
};

export default function AdminQuestionBanksEdit({
    questionBank,
    difficultyOptions,
}: Props) {
    return (
        <>
            <Head title={`Edit ${questionBank.title}`} />

            <div className="space-y-6 p-4">
                <Heading
                    title="Edit Question Library"
                    description={questionBank.title}
                />

                <div className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                    <QuestionBankForm
                        action={update.form.patch(questionBank.id)}
                        submitLabel="Save library"
                        questionBank={questionBank}
                        difficultyOptions={difficultyOptions}
                    />
                </div>
            </div>
        </>
    );
}

AdminQuestionBanksEdit.layout = {
    breadcrumbs: [
        {
            title: 'Question Libraries',
            href: admin.questionBanks.index(),
        },
        {
            title: 'Edit',
            href: admin.questionBanks.edit(0),
        },
    ],
};
