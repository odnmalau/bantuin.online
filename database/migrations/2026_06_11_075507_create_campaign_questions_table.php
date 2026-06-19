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
        Schema::create('campaign_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_bank_question_id')->nullable()->constrained('bank_questions')->nullOnDelete();
            $table->string('type')->index();
            $table->text('prompt');
            $table->jsonb('options')->nullable();
            $table->jsonb('correct_answer')->nullable();
            $table->text('expected_rubric')->nullable();
            $table->unsignedSmallInteger('points')->default(10);
            $table->string('difficulty')->default('medium')->index();
            $table->jsonb('skill_tags')->nullable();
            $table->boolean('ai_generated')->default(false)->index();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['campaign_id', 'sort_order']);
            $table->index(['campaign_section_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_questions');
    }
};
