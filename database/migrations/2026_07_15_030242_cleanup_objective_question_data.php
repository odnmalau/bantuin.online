<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $objectiveTypes = ['multiple_choice', 'yes_no', 'fill_blank', 'matching_pairs'];

        DB::table('campaign_questions')->whereIn('type', $objectiveTypes)->delete();

        DB::table('campaigns')
            ->select(['id', 'ranking_weights'])
            ->orderBy('id')
            ->chunkById(100, function ($campaigns): void {
                foreach ($campaigns as $campaign) {
                    $weights = $this->decodeJson($campaign->ranking_weights);

                    if ($weights === null) {
                        continue;
                    }

                    $resumeWeight = max(0, (int) ($weights['resume_score'] ?? 35));
                    $assessmentWeight = max(0, (int) ($weights['essay_score'] ?? 0))
                        + max(0, (int) ($weights['mcq_score'] ?? 0));

                    if ($resumeWeight + $assessmentWeight !== 100) {
                        $resumeWeight = 35;
                        $assessmentWeight = 65;
                    }

                    DB::table('campaigns')->where('id', $campaign->id)->update([
                        'ranking_weights' => json_encode([
                            'resume_score' => $resumeWeight,
                            'assessment_score' => $assessmentWeight,
                        ], JSON_THROW_ON_ERROR),
                    ]);
                }
            });

        DB::table('assessments')
            ->select(['id', 'answers_payload'])
            ->orderBy('id')
            ->chunkById(100, function ($assessments) use ($objectiveTypes): void {
                foreach ($assessments as $assessment) {
                    $answers = $this->decodeJson($assessment->answers_payload);

                    if ($answers === null) {
                        continue;
                    }

                    $openEndedAnswers = collect($answers)
                        ->filter(fn (mixed $answer): bool => is_array($answer)
                            && ! in_array((string) ($answer['type'] ?? ''), $objectiveTypes, true))
                        ->map(function (array $answer): array {
                            unset($answer['options'], $answer['correct_answer']);

                            return $answer;
                        })
                        ->values()
                        ->all();

                    DB::table('assessments')->where('id', $assessment->id)->update([
                        'answers_payload' => json_encode($openEndedAnswers, JSON_THROW_ON_ERROR),
                        'ranking_payload' => null,
                        'ranking_score' => null,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Deleted objective questions and historical answer snapshots cannot be reconstructed.
    }

    /**
     * @return array<string, mixed>|array<int, mixed>|null
     */
    private function decodeJson(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }
};
