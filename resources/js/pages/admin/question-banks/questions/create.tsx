import { Head } from '@inertiajs/react';
import { store } from '@/actions/App/Http/Controllers/Admin/BankQuestionController';
import { show } from '@/actions/App/Http/Controllers/Admin/QuestionBankController';
import BankQuestionForm from '@/components/admin/bank-question-form';
import Heading from '@/components/heading';
import admin from '@/routes/admin';

type QuestionBank = {
    id: number;
    title: string;
};

type Option = {
    value: string;
    label: string;
};

type QuestionTypeOption = Option & {
    deterministic: boolean;
};

type Props = {
    questionBank: QuestionBank;
    questionTypes: QuestionTypeOption[];
    gradingModeOptions: Option[];
    difficultyOptions: Option[];
};

export default function AdminBankQuestionsCreate({
    questionBank,
    questionTypes,
    gradingModeOptions,
    difficultyOptions,
}: Props) {
    return (
        <>
            <Head title={`Add question to ${questionBank.title}`} />

            <div className="space-y-6 p-4">
                <Heading
                    title="Add Reusable Question"
                    description={questionBank.title}
                />

                <div className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                    <BankQuestionForm
                        action={store.form(questionBank.id)}
                        cancelHref={show.url(questionBank.id)}
                        submitLabel="Add question"
                        questionTypes={questionTypes}
                        gradingModeOptions={gradingModeOptions}
                        difficultyOptions={difficultyOptions}
                    />
                </div>
            </div>
        </>
    );
}

AdminBankQuestionsCreate.layout = {
    breadcrumbs: [
        {
            title: 'Question Libraries',
            href: admin.questionBanks.index(),
        },
        {
            title: 'Add Question',
            href: admin.questionBanks.index(),
        },
    ],
};
