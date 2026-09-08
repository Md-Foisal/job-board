<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Replaces the old job_listings table. company_id/posted_by_id
     * replace the previous employer_profile_id/user_id ownership columns.
     * salary_min_monthly/salary_max_monthly are a denormalized, derived
     * pair (computed by the model) kept for cheap salary sorting/filtering.
     */
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('posted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('employment_type');
            $table->string('workplace_type');
            $table->string('location_city')->nullable();
            $table->string('location_country')->nullable();
            $table->unsignedInteger('min_experience_years')->nullable();
            $table->unsignedInteger('salary_min')->nullable();
            $table->unsignedInteger('salary_max')->nullable();
            $table->string('salary_currency', 3)->nullable();
            $table->string('salary_period')->nullable();
            $table->boolean('salary_negotiable')->default(false);
            $table->unsignedInteger('salary_min_monthly')->nullable();
            $table->unsignedInteger('salary_max_monthly')->nullable();
            $table->string('availability_status')->default('draft');
            $table->string('moderation_status')->default('pending');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
