import { Form, Head, Link } from '@inertiajs/react';
import { Eye, Plus, SquarePen, Trash2 } from 'lucide-react';
import QuestionBankController from '@/actions/App/Http/Controllers/Admin/QuestionBankController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import admin from '@/routes/admin';

type QuestionBankRow = {
    id: number;
    title: string;
    description: string | null;
    skill_area: string | null;
    difficulty: string;
    is_active: boolean;
    questions_count: number;
    created_by: string | null;
    created_at: string;
};

type Props = {
    questionBanks: QuestionBankRow[];
};

export default function AdminQuestionBanksIndex({ questionBanks }: Props) {
    return (
        <>
            <Head title="Question Libraries" />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Question Libraries"
                        description="Build reusable skill-based question banks for campaign imports."
                    />
                    <Button asChild>
                        <Link href={admin.questionBanks.create()}>
                            <Plus />
                            New library
                        </Link>
                    </Button>
                </div>

                {questionBanks.length === 0 ? (
                    <div className="rounded-lg border border-sidebar-border/70 p-8 text-center dark:border-sidebar-border">
                        <h2 className="text-base font-medium">
                            No libraries yet
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Create a reusable question library before importing
                            questions into campaigns.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[900px] text-sm">
                                <thead className="border-b bg-muted/40 text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">
                                            Library
                                        </th>
                                        <th className="w-40 px-4 py-3 font-medium">
                                            Skill
                                        </th>
                                        <th className="w-28 px-4 py-3 font-medium">
                                            Difficulty
                                        </th>
                                        <th className="w-28 px-4 py-3 font-medium">
                                            Questions
                                        </th>
                                        <th className="w-28 px-4 py-3 font-medium">
                                            Status
                                        </th>
                                        <th className="w-40 px-4 py-3 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {questionBanks.map((questionBank) => (
                                        <tr key={questionBank.id}>
                                            <td className="px-4 py-4 align-top">
                                                <div className="max-w-xl space-y-1">
                                                    <p className="font-medium">
                                                        {questionBank.title}
                                                    </p>
                                                    <p className="line-clamp-2 text-muted-foreground">
                                                        {questionBank.description ||
                                                            '-'}
                                                    </p>
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 align-top text-muted-foreground">
                                                {questionBank.skill_area || '-'}
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <Badge variant="outline">
                                                    {questionBank.difficulty}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-4 align-top text-muted-foreground">
                                                {questionBank.questions_count}
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <Badge
                                                    variant={
                                                        questionBank.is_active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {questionBank.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        size="icon"
                                                        variant="outline"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={admin.questionBanks.show(
                                                                questionBank.id,
                                                            )}
                                                            aria-label="View library"
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
                                                            href={admin.questionBanks.edit(
                                                                questionBank.id,
                                                            )}
                                                            aria-label="Edit library"
                                                        >
                                                            <SquarePen />
                                                        </Link>
                                                    </Button>
                                                    <Form
                                                        {...QuestionBankController.destroy.form.delete(
                                                            questionBank.id,
                                                        )}
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                size="icon"
                                                                variant="outline"
                                                                disabled={
                                                                    processing
                                                                }
                                                                aria-label="Delete library"
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

AdminQuestionBanksIndex.layout = {
    breadcrumbs: [
        {
            title: 'Question Libraries',
            href: admin.questionBanks.index(),
        },
    ],
};
