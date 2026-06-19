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
    case RankingReady = 'ranking_ready';
    case NeedsManualReview = 'needs_manual_review';
    case Overridden = 'overridden';
    case Rejected = 'rejected';
    case Approved = 'approved';
    case EmailSent = 'email_sent';
    case EmailFailed = 'email_failed';
    case EvaluationFailed = 'evaluation_failed';

    public function isReviewable(): bool
    {
        return in_array($this, [
            self::PendingApproval,
            self::Evaluated,
            self::NeedsManualReview,
            self::Overridden,
        ], true);
    }

    public function isPromotable(): bool
    {
        return in_array($this, [
            self::Evaluated,
            self::NeedsManualReview,
        ], true);
    }
}
