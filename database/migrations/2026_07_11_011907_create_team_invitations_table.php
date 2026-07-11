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
        Schema::create('team_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->string('email');
            $table->enum('role', ['administrator', 'collaborator']);
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->enum('status', ['pending', 'accepted', 'revoked', 'expired'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status', 'expires_at']);
            $table->index(['team_id', 'email']);
        });

        DB::statement("CREATE UNIQUE INDEX team_invitations_pending_email_unique ON team_invitations (team_id, LOWER(email)) WHERE status = 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
    }
};
