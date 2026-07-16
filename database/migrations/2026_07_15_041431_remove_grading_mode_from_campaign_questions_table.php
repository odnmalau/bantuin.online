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
            $table->dropIndex(['grading_mode']);
            $table->dropColumn('grading_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_questions', function (Blueprint $table) {
            $table->string('grading_mode')->default('ai')->index();
        });
    }
};
