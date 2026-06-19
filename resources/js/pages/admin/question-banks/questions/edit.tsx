import { Form, Head } from '@inertiajs/react';
import { ArrowRightLeft, Sparkles } from 'lucide-react';
import {
    convertToMcq,
    regenerateMcqOptions,
    update,
} from '@/actions/App/Http/Controllers/Admin/BankQuestionController';
import { show } from '@/actions/App/Http/Controllers/Admin/QuestionBankController';
import BankQuestionForm from '@/components/admin/bank-question-form';
import type { BankQuestionFormValues } from '@/components/admin/bank-question-form';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
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
    question: BankQuestionFormValues & {
        id: number;
        status: string;
    };
    questionTypes: QuestionTypeOption[];
    gradingModeOptions: Option[];
    difficultyOptions: Option[];
};

export default function AdminBankQuestionsEdit({
    questionBank,
    question,
    questionTypes,
    gradingModeOptions,
    difficultyOptions,
}: Props) {
    const canRegenerateMcq =
        question.type === 'multiple_choice' && question.status === 'draft';
    const canConvertToMcq =
        (question.type === 'short_text' || question.type === 'long_text') &&
        question.status === 'draft';

    return (
        <>
            <Head title="Edit reusable question" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Edit Reusable Question"
                    description={questionBank.title}
                />

                {(canRegenerateMcq || canConvertToMcq) && (
                    <div className="flex flex-wrap items-center gap-3">
                        {canRegenerateMcq ? (
                            <Form
                                {...regenerateMcqOptions.form([
                                    questionBank.id,
                                    question.id,
                                ])}
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <Button
                                            type="submit"
                                            variant="secondary"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            <Sparkles />
                                            Regenerate MCQ options
                                        </Button>
                                        <InputError
                                            message={errors.regeneration}
                                        />
                                    </>
                                )}
                            </Form>
                        ) : null}
                        {canConvertToMcq ? (
                            <Form
                                {...convertToMcq.form([
                                    questionBank.id,
                                    question.id,
                                ])}
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            <ArrowRightLeft />
                                            Convert to MCQ
                                        </Button>
                                        <InputError
                                            message={errors.conversion}
                                        />
                                    </>
                                )}
                            </Form>
                        ) : null}
                    </div>
                )}

                <div className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                    <BankQuestionForm
                        action={update.form.patch([
                            questionBank.id,
                            question.id,
                        ])}
                        cancelHref={show.url(questionBank.id)}
                        submitLabel="Save question"
                        question={question}
                        questionTypes={questionTypes}
                        gradingModeOptions={gradingModeOptions}
                        difficultyOptions={difficultyOptions}
                    />
                </div>
            </div>
        </>
    );
}

AdminBankQuestionsEdit.layout = {
    breadcrumbs: [
        {
            title: 'Question Libraries',
            href: admin.questionBanks.index(),
        },
        {
            title: 'Edit Question',
            href: admin.questionBanks.index(),
        },
    ],
};
