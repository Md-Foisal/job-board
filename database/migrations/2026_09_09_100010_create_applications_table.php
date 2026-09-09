<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * candidate_profile_id (not user_id) so an application always traces
     * back to the applicant's professional identity, matching how the
     * rest of the candidate-side schema is keyed. resume_document_id
     * snapshots whichever Document was the CV at the moment of applying --
     * it stays pointing at that same row even if the candidate later
     * replaces their CV in the document library.
     */
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained()->restrictOnDelete();
            $table->foreignId('candidate_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('resume_document_id')->constrained('documents')->restrictOnDelete();
            $table->text('cover_letter')->nullable();
            $table->string('outcome_status')->default('active');
            $table->string('stage')->default('new');
            $table->timestamps();

            $table->unique(['job_posting_id', 'candidate_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
