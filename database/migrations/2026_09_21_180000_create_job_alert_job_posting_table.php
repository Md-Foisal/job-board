<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which postings each alert has already emailed. A time window alone
     * (last_sent_at) misses a posting approved after the run that covered
     * the hour it was submitted in; remembering what was sent does not.
     */
    public function up(): void
    {
        Schema::create('job_alert_job_posting', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['job_alert_id', 'job_posting_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_alert_job_posting');
    }
};
