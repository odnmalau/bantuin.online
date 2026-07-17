import { Head, usePoll } from '@inertiajs/react';
import { CalendarClock, CheckCircle2, FileText } from 'lucide-react';
import { Fragment, useEffect } from 'react';
import AssessmentController from '@/actions/App/Http/Controllers/Candidate/AssessmentController';
import AssessmentStatusBadge from '@/components/assessment-status-badge';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Item,
    ItemContent,
    ItemDescription,
    ItemGroup,
    ItemMedia,
    ItemSeparator,
    ItemTitle,
} from '@/components/ui/item';
import candidate from '@/routes/candidate';

type AnswerSnapshot = {
    question_id: number;
    section_id: number | null;
    section_title: string | null;
    question: string;
    answer: string;
};

type AnswerSection = {
    key: string;
    title: string;
    answers: AnswerSnapshot[];
};

type Assessment = {
    id: number;
    campaign_id: number | null;
    campaign: {
        title: string;
        role_title: string;
        team: {
            name: string;
        };
    } | null;
    answers_payload: AnswerSnapshot[];
    resume_original_name: string | null;
    status: string;
    created_at: string;
    evaluated_at: string | null;
    email_sent_at: string | null;
};

type Props = {
    assessment: Assessment;
};

const PROCESSING_STATUSES = [
    'submitted',
    'resume_processing',
    'resume_screening',
    'evaluating',
];

export default function CandidateAssessmentShow({ assessment }: Props) {
    const answerSections = groupAnswersBySection(assessment.answers_payload);
    const { start, stop } = usePoll(
        2000,
        { only: ['assessment'] },
        { autoStart: false, mode: 'rest' },
    );

    useEffect(() => {
        if (PROCESSING_STATUSES.includes(assessment.status)) {
            start();

            return;
        }

        stop();
    }, [assessment.status, start, stop]);

    return (
        <>
            <Head title="Assessment Status" />

            <div className="flex w-full flex-col gap-6 p-4">
                <Card className="gap-0 bg-background-200">
                    <CardHeader>
                        <CardTitle>
                            {assessment.campaign?.title ?? 'Assessment'}
                        </CardTitle>
                        <CardDescription>
                            {assessment.campaign
                                ? `${assessment.campaign.team.name} · ${assessment.campaign.role_title}`
                                : 'Submitted assessment'}
                        </CardDescription>
                        <CardAction>
                            <AssessmentStatusBadge status={assessment.status} />
                        </CardAction>
                    </CardHeader>
                    <CardContent className="py-(--card-spacing)">
                        <ItemGroup className="grid gap-4 md:grid-cols-3">
                            <Item variant="outline" className="bg-background">
                                <ItemMedia variant="icon">
                                    <FileText />
                                </ItemMedia>
                                <ItemContent>
                                    <ItemTitle>Resume</ItemTitle>
                                    <ItemDescription className="truncate">
                                        {assessment.resume_original_name ?? '-'}
                                    </ItemDescription>
                                </ItemContent>
                            </Item>
                            <Item variant="outline" className="bg-background">
                                <ItemMedia variant="icon">
                                    <CalendarClock />
                                </ItemMedia>
                                <ItemContent>
                                    <ItemTitle>Submitted</ItemTitle>
                                    <ItemDescription>
                                        {new Date(
                                            assessment.created_at,
                                        ).toLocaleString()}
                                    </ItemDescription>
                                </ItemContent>
                            </Item>
                            <Item variant="outline" className="bg-background">
                                <ItemMedia variant="icon">
                                    <CheckCircle2 />
                                </ItemMedia>
                                <ItemContent>
                                    <ItemTitle>Evaluated</ItemTitle>
                                    <ItemDescription>
                                        {assessment.evaluated_at
                                            ? new Date(
                                                  assessment.evaluated_at,
                                              ).toLocaleString()
                                            : 'Pending'}
                                    </ItemDescription>
                                </ItemContent>
                            </Item>
                        </ItemGroup>
                    </CardContent>
                </Card>

                <Card className="gap-0 overflow-hidden">
                    <CardHeader className="border-b">
                        <CardTitle>Submitted answers</CardTitle>
                        <CardDescription>
                            A read-only record of the answers included in your
                            assessment.
                        </CardDescription>
                        <CardAction>
                            <Badge variant="secondary">
                                {assessment.answers_payload.length} answers
                            </Badge>
                        </CardAction>
                    </CardHeader>
                    <CardContent className="bg-background-200 py-(--card-spacing)">
                        <Accordion
                            type="multiple"
                            className="overflow-hidden rounded-md border bg-background"
                            defaultValue={answerSections.map(
                                (section) => section.key,
                            )}
                        >
                            {answerSections.map((section) => (
                                <AccordionItem
                                    key={section.key}
                                    value={section.key}
                                >
                                    <AccordionTrigger className="px-(--card-spacing) hover:no-underline">
                                        <span className="flex items-center gap-2">
                                            {section.title}
                                            <Badge variant="secondary">
                                                {section.answers.length}
                                            </Badge>
                                        </span>
                                    </AccordionTrigger>
                                    <AccordionContent className="px-(--card-spacing)">
                                        <ItemGroup className="gap-0 overflow-hidden rounded-md border">
                                            {section.answers.map(
                                                (answer, index) => (
                                                    <Fragment
                                                        key={answer.question_id}
                                                    >
                                                        {index > 0 ? (
                                                            <ItemSeparator className="my-0" />
                                                        ) : null}
                                                        <Item
                                                            size="sm"
                                                            className="rounded-none border-0"
                                                        >
                                                            <ItemContent>
                                                                <ItemTitle className="line-clamp-none">
                                                                    Question{' '}
                                                                    {index + 1}{' '}
                                                                    ·{' '}
                                                                    {
                                                                        answer.question
                                                                    }
                                                                </ItemTitle>
                                                                <ItemDescription className="line-clamp-none whitespace-pre-wrap">
                                                                    {
                                                                        answer.answer
                                                                    }
                                                                </ItemDescription>
                                                            </ItemContent>
                                                        </Item>
                                                    </Fragment>
                                                ),
                                            )}
                                        </ItemGroup>
                                    </AccordionContent>
                                </AccordionItem>
                            ))}
                        </Accordion>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function groupAnswersBySection(answers: AnswerSnapshot[]): AnswerSection[] {
    const sections = new Map<string, AnswerSection>();

    for (const answer of answers) {
        const key = answer.section_id
            ? `section-${answer.section_id}`
            : `section-${answer.section_title ?? 'assessment'}`;
        const existing = sections.get(key);

        if (existing) {
            existing.answers.push(answer);

            continue;
        }

        sections.set(key, {
            key,
            title: answer.section_title ?? 'Assessment',
            answers: [answer],
        });
    }

    return Array.from(sections.values());
}

CandidateAssessmentShow.layout = (props: Partial<Props>) => {
    const assessment = props.assessment;
    const examHref =
        assessment?.campaign_id !== null &&
        assessment?.campaign_id !== undefined
            ? AssessmentController.campaignExam.url(assessment.campaign_id)
            : candidate.exam();

    return {
        breadcrumbs: [
            {
                title: 'Candidate Exam',
                href: examHref,
            },
            {
                title: 'Assessment Status',
                href: examHref,
            },
        ],
    };
};
