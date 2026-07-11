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
        Schema::create('ownership_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_membership_id')->constrained('team_memberships')->restrictOnDelete();
            $table->foreignId('recipient_membership_id')->constrained('team_memberships')->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->enum('status', ['pending', 'accepted', 'revoked', 'expired'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status', 'expires_at']);
        });

        DB::statement("CREATE UNIQUE INDEX ownership_transfers_pending_team_unique ON ownership_transfers (team_id) WHERE status = 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ownership_transfers');
    }
};
