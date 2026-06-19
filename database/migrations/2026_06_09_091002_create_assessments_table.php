<?php

use App\AssessmentStatus;
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
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('answers_payload');
            $table->unsignedTinyInteger('ai_score')->nullable()->index();
            $table->text('ai_justification')->nullable();
            $table->string('ai_email_subject')->nullable();
            $table->text('ai_email_body')->nullable();
            $table->string('approved_email_subject')->nullable();
            $table->text('approved_email_body')->nullable();
            $table->string('status')->default(AssessmentStatus::Submitted->value)->index();
            $table->timestamp('evaluated_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
