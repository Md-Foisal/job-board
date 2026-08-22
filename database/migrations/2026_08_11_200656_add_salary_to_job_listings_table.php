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
        Schema::table('job_listings', function (Blueprint $table) {
            $table->unsignedInteger('salary_min')->nullable();
            $table->unsignedInteger('salary_max')->nullable();
            $table->string('salary_currency', 3)->nullable();
            $table->enum('salary_period', ['hourly', 'weekly', 'monthly', 'yearly', 'contract'])->nullable();
            $table->unsignedInteger('salary_min_monthly')->nullable();
            $table->unsignedInteger('salary_max_monthly')->nullable();
            $table->dropColumn('salary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->dropColumn(['salary_min', 'salary_max', 'salary_currency', 'salary_period', 'salary_min_monthly', 'salary_max_monthly']);
            $table->string('salary')->nullable();
        });
    }
};
