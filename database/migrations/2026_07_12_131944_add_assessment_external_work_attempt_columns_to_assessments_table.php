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
            $table->uuid('resume_screening_attempt_id')->nullable()->after('email_sent_at');
            $table->timestamp('resume_screening_started_at')->nullable()->after('resume_screening_attempt_id');
            $table->uuid('evaluation_attempt_id')->nullable()->after('resume_screening_started_at');
            $table->timestamp('evaluation_started_at')->nullable()->after('evaluation_attempt_id');
            $table->uuid('email_delivery_attempt_id')->nullable()->after('evaluation_started_at');
            $table->timestamp('email_delivery_started_at')->nullable()->after('email_delivery_attempt_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn([
                'resume_screening_attempt_id',
                'resume_screening_started_at',
                'evaluation_attempt_id',
                'evaluation_started_at',
                'email_delivery_attempt_id',
                'email_delivery_started_at',
            ]);
        });
    }
};
