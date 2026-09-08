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
        Schema::create('category_job_posting', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();

            $table->unique(['job_posting_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_job_posting');
    }
};
