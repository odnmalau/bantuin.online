<?php

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
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropUnique(['user_id']);

            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('resume_path')->nullable();
            $table->string('resume_original_name')->nullable();
            $table->text('resume_text')->nullable();
            $table->unsignedTinyInteger('resume_score')->nullable()->index();
            $table->text('resume_justification')->nullable();
            $table->jsonb('resume_payload')->nullable();
            $table->unsignedTinyInteger('mcq_score')->nullable()->index();
            $table->unsignedTinyInteger('essay_score')->nullable()->index();
            $table->unsignedTinyInteger('ranking_score')->nullable()->index();
            $table->jsonb('ranking_payload')->nullable();
            $table->jsonb('critic_payload')->nullable();
            $table->boolean('needs_manual_review')->default(false)->index();

            $table->unique(['user_id', 'campaign_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'campaign_id']);
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropColumn([
                'resume_path',
                'resume_original_name',
                'resume_text',
                'resume_score',
                'resume_justification',
                'resume_payload',
                'mcq_score',
                'essay_score',
                'ranking_score',
                'ranking_payload',
                'critic_payload',
                'needs_manual_review',
            ]);

            $table->unique('user_id');
        });
    }
};
