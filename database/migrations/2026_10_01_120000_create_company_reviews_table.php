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
     * proves the writer really went through the process, and the unique
     * key keeps it to one review per application. Restricting deletion
     * keeps that proof from disappearing out from under a published
     * review. company_id is the same company the application leads to,
     * stored once at write time so the company page never has to join
     * through postings to find its reviews.
     *
     * The review and the response are moderated separately, because a
     * company's answer can expose a reviewer just as easily as the review
     * can. published_at records the first approval and is what readers
     * are shown, by month.
     */
    public function up(): void
    {
        Schema::create('company_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('overall_rating');
            $table->unsignedTinyInteger('communication_rating');
            $table->string('job_as_described');
            $table->string('title', 100);
            $table->text('body');
            $table->string('moderation_status')->default('pending');
            $table->timestamp('published_at')->nullable();
            $table->text('response_body')->nullable();
            $table->string('response_status')->nullable();
            $table->foreignId('responded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'moderation_status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_reviews');
    }
};
