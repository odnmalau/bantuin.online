<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('campaign_questions', function (Blueprint $table) {
            $table->string('grading_mode')->default('ai')->after('type')->index();
        });

        DB::table('campaign_questions')
            ->whereIn('type', ['multiple_choice', 'yes_no', 'fill_blank', 'matching_pairs'])
            ->update(['grading_mode' => 'deterministic']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_questions', function (Blueprint $table) {
            $table->dropIndex(['grading_mode']);
            $table->dropColumn('grading_mode');
        });
    }
};
