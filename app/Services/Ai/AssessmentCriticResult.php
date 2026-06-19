<?php

namespace App\Services\Ai;

use Illuminate\Support\Arr;

class AssessmentCriticResult
{
    /**
     * @param  array<int, string>  $findings
     */
    public function __construct(
        public readonly string $outcome,
        public readonly string $summary,
        public readonly array $findings,
        public readonly bool $manualReviewRequired,
        public readonly ?string $repairedEmailSubject = null,
        public readonly ?string $repairedEmailBody = null,
    ) {
        if (! in_array($this->outcome, ['passed', 'repaired', 'needs_manual_review', 'failed'], true)) {
            throw AssessmentCriticException::invalidOutput('outcome is invalid.');
        }

        if (trim($this->summary) === '') {
            throw AssessmentCriticException::invalidOutput('summary must be present.');
        }

        if ($this->outcome === 'repaired' && (blank($this->repairedEmailSubject) || blank($this->repairedEmailBody))) {
            throw AssessmentCriticException::invalidOutput('repaired outcome requires repaired email subject and body.');
        }
    }

    /**
     * @param  array<string, mixed>  $output
     */
    public static function fromStructuredOutput(array $output): self
    {
        $outcome = Arr::get($output, 'outcome');
        $summary = Arr::get($output, 'summary');
        $findings = Arr::get($output, 'findings');
        $manualReviewRequired = Arr::get($output, 'manual_review_required');
        $repairedEmailSubject = Arr::get($output, 'repaired_email.subject');
        $repairedEmailBody = Arr::get($output, 'repaired_email.body');

        if (! is_string($outcome) || ! is_string($summary) || ! is_bool($manualReviewRequired)) {
            throw AssessmentCriticException::invalidOutput('outcome, summary, and manual_review_required must be present.');
        }

        return new self(
            outcome: $outcome,
            summary: $summary,
            findings: StructuredOutputLists::nonEmptyStringList($findings, 'findings', AssessmentCriticException::class),
            manualReviewRequired: $manualReviewRequired,
            repairedEmailSubject: is_string($repairedEmailSubject) ? $repairedEmailSubject : null,
            repairedEmailBody: is_string($repairedEmailBody) ? $repairedEmailBody : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'outcome' => $this->outcome,
            'summary' => $this->summary,
            'findings' => $this->findings,
            'manual_review_required' => $this->manualReviewRequired,
            'repaired_email' => [
                'subject' => $this->repairedEmailSubject,
                'body' => $this->repairedEmailBody,
            ],
        ];
    }

    public function blocksAutopilotApproval(): bool
    {
        return $this->manualReviewRequired || in_array($this->outcome, ['needs_manual_review', 'failed'], true);
    }
}
