<?php

namespace App\Services\Ai;

use Illuminate\Support\Arr;

class ResumeScreeningResult
{
    /**
     * @param  array<int, string>  $matchedSkills
     * @param  array<int, string>  $missingSkills
     * @param  array<int, string>  $riskFlags
     * @param  array<int, string>  $interviewProbes
     */
    public function __construct(
        public readonly int $score,
        public readonly string $summary,
        public readonly array $matchedSkills,
        public readonly array $missingSkills,
        public readonly array $riskFlags,
        public readonly array $interviewProbes,
        public readonly int $confidence,
        public readonly string $justification,
    ) {
        if ($this->score < 0 || $this->score > 100) {
            throw ResumeScreeningException::invalidOutput('resume_score must be between 0 and 100.');
        }

        if ($this->confidence < 0 || $this->confidence > 100) {
            throw ResumeScreeningException::invalidOutput('confidence must be between 0 and 100.');
        }

        if (trim($this->summary) === '' || trim($this->justification) === '') {
            throw ResumeScreeningException::invalidOutput('summary and justification must be present.');
        }
    }

    /**
     * @param  array<string, mixed>  $output
     */
    public static function fromStructuredOutput(array $output): self
    {
        $score = Arr::get($output, 'resume_score');
        $summary = Arr::get($output, 'summary');
        $matchedSkills = Arr::get($output, 'matched_skills');
        $missingSkills = Arr::get($output, 'missing_skills');
        $riskFlags = Arr::get($output, 'risk_flags');
        $interviewProbes = Arr::get($output, 'interview_probes');
        $confidence = Arr::get($output, 'confidence');
        $justification = Arr::get($output, 'justification');

        if (! is_int($score) || ! is_int($confidence)) {
            throw ResumeScreeningException::invalidOutput('resume_score and confidence must be integers.');
        }

        if (! is_string($summary) || ! is_string($justification)) {
            throw ResumeScreeningException::invalidOutput('summary and justification must be strings.');
        }

        return new self(
            score: $score,
            summary: $summary,
            matchedSkills: StructuredOutputLists::nonEmptyStringList($matchedSkills, 'matched_skills', ResumeScreeningException::class),
            missingSkills: StructuredOutputLists::nonEmptyStringList($missingSkills, 'missing_skills', ResumeScreeningException::class),
            riskFlags: StructuredOutputLists::nonEmptyStringList($riskFlags, 'risk_flags', ResumeScreeningException::class),
            interviewProbes: StructuredOutputLists::nonEmptyStringList($interviewProbes, 'interview_probes', ResumeScreeningException::class),
            confidence: $confidence,
            justification: $justification,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'summary' => $this->summary,
            'matched_skills' => $this->matchedSkills,
            'missing_skills' => $this->missingSkills,
            'risk_flags' => $this->riskFlags,
            'interview_probes' => $this->interviewProbes,
            'confidence' => $this->confidence,
            'justification' => $this->justification,
        ];
    }
}
