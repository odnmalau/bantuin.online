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
        if (Schema::hasColumn('campaigns', 'nice_to_have_skills')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->dropColumn('nice_to_have_skills');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('campaigns', 'nice_to_have_skills')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->jsonb('nice_to_have_skills')->nullable();
            });
        }
    }
};
