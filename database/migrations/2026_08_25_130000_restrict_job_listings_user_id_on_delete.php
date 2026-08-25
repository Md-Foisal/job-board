<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 4 (DB-level deletion safety): `job_listings.user_id` was
     * originally defined in the protected/merged create_job_listings_table
     * migration with cascadeOnDelete(), so a real hard delete of a user
     * would silently wipe out every job listing they ever posted (and, by
     * further cascade through job_listings.employer_profile_id, any
     * applications candidates submitted to those listings).
     *
     * That create migration is already merged into main and must not be
     * edited directly (Migration Hygiene rule) -- so this is a new,
     * additive migration that swaps the FK's ON DELETE behavior instead.
     *
     * restrictOnDelete() means the database itself will now refuse a real
     * DELETE FROM users ... while that user still owns any job listing at
     * all (open, closed, or expired) -- a structural backstop underneath
     * the application-level guard (JobListingController::destroy() and the
     * account-deletion check in the settings page), which is what actually
     * enforces the "only if the listing is currently ACTIVE" business rule
     * that a DB constraint has no way to express on its own.
     *
     * Note this normally never fires in practice: User::delete() is a soft
     * delete (an UPDATE, not a real DELETE), so this constraint only ever
     * matters against a raw/forced hard delete that bypasses the app.
     */
    public function up(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('job_listings', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('job_listings', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
