import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline';

type Props = {
    status: string;
};

const statuses: Record<
    string,
    { label: string; variant: BadgeVariant; processing?: boolean }
> = {
    submitted: {
        label: 'Submitted',
        variant: 'secondary',
        processing: true,
    },
    evaluating: {
        label: 'Evaluating',
        variant: 'secondary',
        processing: true,
    },
    resume_processing: {
        label: 'Resume processing',
        variant: 'secondary',
        processing: true,
    },
    resume_screening: {
        label: 'Resume screening',
        variant: 'secondary',
        processing: true,
    },
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

    return (
        <Badge
            variant={statusConfig.variant}
            role={statusConfig.processing ? 'status' : undefined}
            aria-live={statusConfig.processing ? 'polite' : undefined}
        >
            {statusConfig.processing ? <Spinner aria-hidden="true" /> : null}
            <span
                className={
                    statusConfig.processing ? 'ai-status-shimmer' : undefined
                }
            >
                {statusConfig.label}
            </span>
        </Badge>
    );
}
