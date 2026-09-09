<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->integer('desired_salary_min')->nullable();
            $table->integer('desired_salary_max')->nullable();
            $table->string('desired_salary_currency')->nullable();
            $table->string('preferred_workplace_type')->nullable();
            $table->string('preferred_employment_type')->nullable();
            $table->boolean('is_actively_searching')->default(true);
            $table->date('available_from')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_preferences');
    }
};
