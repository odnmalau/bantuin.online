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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('status', ['active', 'deactivated'])
                ->default('active')
                ->index();
            $table->timestamp('deactivated_at')->nullable();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('team_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('role', ['owner', 'administrator', 'collaborator']);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'ended_at']);
            $table->index(['team_id', 'role', 'ended_at']);
        });

        Schema::create('platform_operator_authorities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });

        Schema::create('team_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_context');
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->jsonb('before_state')->nullable();
            $table->jsonb('after_state')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['team_id', 'occurred_at']);
            $table->index(['actor_id', 'occurred_at']);
            $table->index('action');
        });

        DB::statement('CREATE UNIQUE INDEX team_memberships_active_user_unique ON team_memberships (team_id, user_id) WHERE ended_at IS NULL');
        DB::statement("CREATE UNIQUE INDEX team_memberships_effective_owner_unique ON team_memberships (team_id) WHERE role = 'owner' AND ended_at IS NULL");
        DB::statement('CREATE UNIQUE INDEX platform_operator_authorities_active_user_unique ON platform_operator_authorities (user_id) WHERE revoked_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_activities');
        Schema::dropIfExists('platform_operator_authorities');
        Schema::dropIfExists('team_memberships');
        Schema::dropIfExists('teams');
    }
};
