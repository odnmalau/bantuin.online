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
        Schema::table('campaign_invitations', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
        });

        Schema::table('campaign_invitations', function (Blueprint $table) {
            $table->foreign('campaign_id')
                ->references('id')
                ->on('campaigns')
                ->restrictOnDelete();
        });

        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
        });

        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->foreign('campaign_id')
                ->references('id')
                ->on('campaigns')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_invitations', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
        });

        Schema::table('campaign_invitations', function (Blueprint $table) {
            $table->foreign('campaign_id')
                ->references('id')
                ->on('campaigns')
                ->cascadeOnDelete();
        });

        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
        });

        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->foreign('campaign_id')
                ->references('id')
                ->on('campaigns')
                ->cascadeOnDelete();
        });
    }
};
