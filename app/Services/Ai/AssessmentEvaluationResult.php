<?php

namespace App\Services\Ai;

use Illuminate\Support\Arr;

class AssessmentEvaluationResult
{
    /**
     * @param  string|null  $emailSubject  Required when score meets threshold.
     * @param  string|null  $emailBody  Required when score meets threshold.
     */
    public function __construct(
        public readonly int $score,
        public readonly string $justification,
        public readonly ?string $emailSubject = null,
        public readonly ?string $emailBody = null,
    ) {
        if ($this->score < 0 || $this->score > 100) {
            throw AssessmentEvaluationException::invalidOutput('score must be between 0 and 100.');
        }

        if (trim($this->justification) === '') {
            throw AssessmentEvaluationException::invalidOutput('justification must be present.');
        }
    }

    /**
     * @param  array<string, mixed>  $output
     */
    public static function fromStructuredOutput(array $output, int $passingScore): self
    {
        $score = Arr::get($output, 'score');
        $justification = Arr::get($output, 'justification');
        $emailSubject = Arr::get($output, 'email.subject');
        $emailBody = Arr::get($output, 'email.body');

        if (! is_int($score)) {
            throw AssessmentEvaluationException::invalidOutput('score must be an integer.');
        }

        if (! is_string($justification)) {
            throw AssessmentEvaluationException::invalidOutput('justification must be a string.');
        }

        if ($score >= $passingScore) {
            $subjectValid = is_string($emailSubject) && trim($emailSubject) !== '';
            $bodyValid = is_string($emailBody) && trim($emailBody) !== '';

            if (! $subjectValid || ! $bodyValid) {
                throw AssessmentEvaluationException::invalidOutput('email subject and body are required when score meets threshold.');
            }
        }

        return new self(
            score: $score,
            justification: $justification,
            emailSubject: is_string($emailSubject) ? $emailSubject : null,
            emailBody: is_string($emailBody) ? $emailBody : null,
        );
    }
}
