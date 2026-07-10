<?php

namespace App;

enum AssessmentStatus: string
{
    case Submitted = 'submitted';
    case Evaluating = 'evaluating';
    case ResumeProcessing = 'resume_processing';
    case ResumeScreening = 'resume_screening';
    case PendingApproval = 'pending_approval';
    case Evaluated = 'evaluated';
    case NeedsManualReview = 'needs_manual_review';
    case Overridden = 'overridden';
    case Rejected = 'rejected';
    case Approved = 'approved';
    case EmailSent = 'email_sent';
    case EmailFailed = 'email_failed';
    case EvaluationFailed = 'evaluation_failed';

    public function isReviewable(): bool
    {
        return match ($this) {
            self::PendingApproval,
            self::Evaluated,
            self::NeedsManualReview,
            self::Overridden => true,
            default => false,
        };
    }

    public function isPromotable(): bool
    {
        return match ($this) {
            self::Evaluated,
            self::NeedsManualReview => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Evaluating => 'Evaluating',
            self::ResumeProcessing => 'Resume processing',
            self::ResumeScreening => 'Resume screening',
            self::PendingApproval => 'Pending approval',
            self::Evaluated => 'Evaluated',
            self::NeedsManualReview => 'Needs review',
            self::Overridden => 'Overridden',
            self::Rejected => 'Rejected',
            self::Approved => 'Approved',
            self::EmailSent => 'Email sent',
            self::EmailFailed => 'Email failed',
            self::EvaluationFailed => 'Evaluation failed',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function selectOptions(): array
    {
        return array_map(
            fn (self $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            self::cases(),
        );
    }
}
