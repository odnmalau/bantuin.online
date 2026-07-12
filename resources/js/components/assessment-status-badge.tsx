import { Badge } from '@/components/ui/badge';

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline';

type Props = {
    status: string;
};

const statuses: Record<string, { label: string; variant: BadgeVariant }> = {
    submitted: { label: 'Submitted', variant: 'secondary' },
    evaluating: { label: 'Evaluating', variant: 'secondary' },
    resume_processing: { label: 'Resume processing', variant: 'secondary' },
    resume_screening: { label: 'Resume screening', variant: 'secondary' },
    pending_approval: { label: 'Pending approval', variant: 'default' },
    evaluated: { label: 'Evaluated', variant: 'secondary' },
    ranking_ready: { label: 'Ranking ready', variant: 'default' },
    needs_manual_review: { label: 'Needs review', variant: 'outline' },
    overridden: { label: 'Overridden', variant: 'outline' },
    rejected: { label: 'Rejected', variant: 'destructive' },
    approved: { label: 'Approved', variant: 'outline' },
    email_sending: { label: 'Email sending', variant: 'secondary' },
    email_sent: { label: 'Email sent', variant: 'outline' },
    email_failed: { label: 'Email failed', variant: 'destructive' },
    evaluation_failed: { label: 'Evaluation failed', variant: 'destructive' },
};

export default function AssessmentStatusBadge({ status }: Props) {
    const statusConfig = statuses[status] ?? {
        label: status,
        variant: 'secondary' as const,
    };

    return <Badge variant={statusConfig.variant}>{statusConfig.label}</Badge>;
}
