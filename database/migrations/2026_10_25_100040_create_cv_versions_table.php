<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A candidate's saved CVs: each one a recipe, not a file. It keeps
     * how the CV looks (design), which of the profile's entries it leaves
     * out, a headline and summary of its own when it is aimed at one job,
     * and the parts that belong on a CV but not on the profile (extras:
     * interests, references, personal details, a declaration, sections
     * of the candidate's own). Roles, studies and projects are always
     * read from the profile, so the profile stays the one source.
     *
     * A CV can be aimed at a job on the board (job_posting_id) or at one
     * elsewhere, whose description is pasted in (job_description).
     *
     * It can also be shared as a web page at /cv/{public_slug} until
     * public_expires_at, with contact details only when the candidate
     * allows it, counting views and downloads.
     */
    public function up(): void
    {
        Schema::create('cv_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->json('design');
            $table->json('extras')->nullable();
            $table->json('hidden_entries')->nullable();
            $table->string('headline')->nullable();
            $table->text('summary')->nullable();
            $table->foreignId('job_posting_id')->nullable()->constrained()->nullOnDelete();
            $table->text('job_description')->nullable();
            $table->string('public_slug', 32)->nullable()->unique();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('public_expires_at')->nullable();
            $table->boolean('public_shows_contact')->default(false);
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_versions');
    }
};
