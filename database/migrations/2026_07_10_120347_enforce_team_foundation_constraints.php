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
        if (DB::table('campaigns')->whereNull('team_id')->exists()) {
            throw new RuntimeException('Cannot enforce Team ownership while a Campaign has no Team.');
        }

        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable(false)->change();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION prevent_campaign_team_change() RETURNS trigger AS $$
                BEGIN
                    IF NEW.team_id IS DISTINCT FROM OLD.team_id THEN
                        RAISE EXCEPTION 'Campaign Team ownership is immutable';
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER campaigns_team_immutable
                BEFORE UPDATE OF team_id ON campaigns
                FOR EACH ROW EXECUTE FUNCTION prevent_campaign_team_change();

                CREATE FUNCTION prevent_team_activity_changes() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'Team Activity is append-only';
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER team_activities_update_immutable
                BEFORE UPDATE ON team_activities
                FOR EACH ROW EXECUTE FUNCTION prevent_team_activity_changes();

                CREATE TRIGGER team_activities_delete_immutable
                BEFORE DELETE ON team_activities
                FOR EACH ROW EXECUTE FUNCTION prevent_team_activity_changes();

                CREATE FUNCTION enforce_team_exactly_one_owner() RETURNS trigger AS $$
                DECLARE
                    affected_team_id bigint;
                BEGIN
                    IF TG_TABLE_NAME = 'teams' THEN
                        affected_team_id := NEW.id;
                    ELSIF TG_OP = 'DELETE' THEN
                        affected_team_id := OLD.team_id;
                    ELSE
                        affected_team_id := NEW.team_id;
                    END IF;

                    IF EXISTS (SELECT 1 FROM teams WHERE id = affected_team_id)
                        AND (SELECT COUNT(*) FROM team_memberships WHERE team_id = affected_team_id AND role = 'owner' AND ended_at IS NULL) <> 1 THEN
                        RAISE EXCEPTION 'A Team must have exactly one effective Owner';
                    END IF;

                    IF TG_TABLE_NAME = 'team_memberships' THEN
                        IF TG_OP = 'UPDATE' AND OLD.team_id IS DISTINCT FROM NEW.team_id
                            AND EXISTS (SELECT 1 FROM teams WHERE id = OLD.team_id)
                            AND (SELECT COUNT(*) FROM team_memberships WHERE team_id = OLD.team_id AND role = 'owner' AND ended_at IS NULL) <> 1 THEN
                            RAISE EXCEPTION 'A Team must have exactly one effective Owner';
                        END IF;
                    END IF;

                    RETURN NULL;
                END;
                $$ LANGUAGE plpgsql;

                CREATE CONSTRAINT TRIGGER teams_exactly_one_owner
                AFTER INSERT OR UPDATE ON teams
                DEFERRABLE INITIALLY DEFERRED
                FOR EACH ROW EXECUTE FUNCTION enforce_team_exactly_one_owner();

                CREATE CONSTRAINT TRIGGER team_memberships_exactly_one_owner
                AFTER INSERT OR UPDATE OR DELETE ON team_memberships
                DEFERRABLE INITIALLY DEFERRED
                FOR EACH ROW EXECUTE FUNCTION enforce_team_exactly_one_owner();
                SQL);

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER campaigns_team_immutable
                BEFORE UPDATE OF team_id ON campaigns
                FOR EACH ROW
                WHEN NEW.team_id != OLD.team_id
                BEGIN
                    SELECT RAISE(ABORT, 'Campaign Team ownership is immutable');
                END;

                CREATE TRIGGER team_activities_update_immutable
                BEFORE UPDATE ON team_activities
                FOR EACH ROW
                BEGIN
                    SELECT RAISE(ABORT, 'Team Activity is append-only');
                END;

                CREATE TRIGGER team_activities_delete_immutable
                BEFORE DELETE ON team_activities
                FOR EACH ROW
                BEGIN
                    SELECT RAISE(ABORT, 'Team Activity is append-only');
                END;
                SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS team_activities_delete_immutable ON team_activities;
                DROP TRIGGER IF EXISTS team_activities_update_immutable ON team_activities;
                DROP FUNCTION IF EXISTS prevent_team_activity_changes();
                DROP TRIGGER IF EXISTS team_memberships_exactly_one_owner ON team_memberships;
                DROP TRIGGER IF EXISTS teams_exactly_one_owner ON teams;
                DROP FUNCTION IF EXISTS enforce_team_exactly_one_owner();
                DROP TRIGGER IF EXISTS campaigns_team_immutable ON campaigns;
                DROP FUNCTION IF EXISTS prevent_campaign_team_change();
                SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS team_activities_delete_immutable;
                DROP TRIGGER IF EXISTS team_activities_update_immutable;
                DROP TRIGGER IF EXISTS campaigns_team_immutable;
                SQL);
        }

        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->change();
        });
    }
};
