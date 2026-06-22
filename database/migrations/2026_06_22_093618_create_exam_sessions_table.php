<?php

use App\ExamSessionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default(ExamSessionStatus::InProgress->value)->index();
            $table->foreignId('current_section_id')->nullable()->constrained('campaign_sections')->nullOnDelete();
            $table->timestamp('current_section_started_at')->nullable();
            $table->timestamp('current_section_expires_at')->nullable();
            $table->json('completed_section_ids')->nullable();
            $table->unsignedSmallInteger('warning_count')->default(0);
            $table->json('integrity_events')->nullable();
            $table->json('answer_drafts')->nullable();
            $table->string('resume_path')->nullable();
            $table->string('resume_original_name')->nullable();
            $table->string('submission_reason')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'campaign_id']);
            $table->index(['campaign_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_sessions');
    }
};
