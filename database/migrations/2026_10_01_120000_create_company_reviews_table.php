<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A candidate's account of one company's hiring process, and the
     * company's single public answer to it.
     *
     * Each review hangs off the application it describes: that is what
     * proves the writer really went through the process. Restricting
     * deletion keeps that proof from disappearing out from under a
     * published review. company_id and candidate_profile_id are the
     * company and the candidate that application leads to, stored once at
     * write time: the company page never has to join through postings to
     * find its reviews, and the unique pair gives each person one voice
     * per company however many times they applied there -- otherwise one
     * applicant could fill a small company's page and its averages alone.
     *
     * The review and the response are moderated separately, because a
     * company's answer can expose a reviewer just as easily as the review
     * can. published_at is when the current text was approved, shown to
     * readers by month; an edit clears it.
     *
     * screening and response_screening hold the AI's hint to staff on the
     * current text of each, when the AI is switched on; it never decides,
     * and an edit clears it.
     */
    public function up(): void
    {
        Schema::create('company_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('candidate_profile_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('overall_rating');
            $table->unsignedTinyInteger('communication_rating');
            $table->string('job_as_described');
            $table->string('title', 100);
            $table->text('body');
            $table->string('moderation_status')->default('pending');
            $table->timestamp('published_at')->nullable();
            $table->json('screening')->nullable();
            $table->text('response_body')->nullable();
            $table->string('response_status')->nullable();
            $table->foreignId('responded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->json('response_screening')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'candidate_profile_id']);
            $table->index(['company_id', 'moderation_status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_reviews');
    }
};
