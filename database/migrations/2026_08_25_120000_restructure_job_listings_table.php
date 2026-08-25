<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Bundles what used to be 4 separate unmerged migrations
     * (employer_profile_id, moderation_status, expires_at, salary) plus new
     * Phase 1 work: splitting the old `type` enum into `employment_type` +
     * `work_location`, and dropping the free-text `company` column (now
     * derived from EmployerProfile::displayName()).
     */
    public function up(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->foreignId('employer_profile_id')->constrained()->restrictOnDelete();
            $table->string('moderation_status')->default('pending')->after('status');
            $table->timestamp('expires_at')->nullable()->after('moderation_status');
            $table->unsignedInteger('salary_min')->nullable();
            $table->unsignedInteger('salary_max')->nullable();
            $table->string('salary_currency', 3)->nullable();
            $table->enum('salary_period', ['hourly', 'weekly', 'monthly', 'yearly', 'contract'])->nullable();
            $table->unsignedInteger('salary_min_monthly')->nullable();
            $table->unsignedInteger('salary_max_monthly')->nullable();
            $table->string('employment_type')->nullable()->after('type');
            $table->string('work_location')->nullable()->after('employment_type');
        });

        // The old `type` column mixed two different axes (employment structure
        // vs work location) into one enum. Split any existing rows into the
        // two new columns before dropping it, so no data is silently lost.
        DB::table('job_listings')->where('type', 'remote')->update([
            'employment_type' => 'full-time',
            'work_location' => 'remote',
        ]);
        DB::table('job_listings')->where('type', '!=', 'remote')->update([
            'work_location' => 'onsite',
        ]);
        DB::statement("UPDATE job_listings SET employment_type = type WHERE type != 'remote'");

        Schema::table('job_listings', function (Blueprint $table) {
            $table->dropColumn(['salary', 'type', 'company']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->string('salary')->nullable();
            $table->enum('type', ['full-time', 'part-time', 'remote', 'contract', 'internship'])->nullable();
            $table->string('company')->nullable();
        });

        DB::statement("UPDATE job_listings SET type = employment_type");
        DB::table('job_listings')->where('work_location', 'remote')->update(['type' => 'remote']);

        Schema::table('job_listings', function (Blueprint $table) {
            $table->dropForeign(['employer_profile_id']);
            $table->dropColumn([
                'employer_profile_id',
                'moderation_status',
                'expires_at',
                'salary_min',
                'salary_max',
                'salary_currency',
                'salary_period',
                'salary_min_monthly',
                'salary_max_monthly',
                'employment_type',
                'work_location',
            ]);
        });
    }
};
