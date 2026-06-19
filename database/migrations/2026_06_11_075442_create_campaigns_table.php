<?php

use App\CampaignStatus;
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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('role_title');
            $table->string('seniority')->nullable();
            $table->text('job_description')->nullable();
            $table->jsonb('required_skills')->nullable();
            $table->unsignedTinyInteger('threshold_score')->default(75);
            $table->string('status')->default(CampaignStatus::Draft->value)->index();
            $table->text('ai_generation_notes')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
