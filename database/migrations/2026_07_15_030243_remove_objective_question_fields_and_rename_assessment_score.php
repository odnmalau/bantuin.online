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
        Schema::table('campaign_questions', function (Blueprint $table) {
            $table->dropColumn(['options', 'correct_answer']);
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropIndex(['mcq_score']);
            $table->dropIndex(['essay_score']);
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn('mcq_score');
            $table->renameColumn('essay_score', 'assessment_score');
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->index('assessment_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_questions', function (Blueprint $table) {
            $table->jsonb('options')->nullable();
            $table->jsonb('correct_answer')->nullable();
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropIndex(['assessment_score']);
            $table->renameColumn('assessment_score', 'essay_score');
            $table->unsignedTinyInteger('mcq_score')->nullable();
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->index('essay_score');
            $table->index('mcq_score');
        });
    }
};
