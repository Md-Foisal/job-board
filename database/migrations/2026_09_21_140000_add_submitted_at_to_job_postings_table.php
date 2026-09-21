<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a posting last went into the review queue. published_at is when
     * it first went public and never moves, so a posting edited or resent
     * after rejection looked as if it had been waiting since its first
     * publication -- ordering the queue and the overdue warning by the
     * wrong clock.
     */
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('published_at');
        });

        // Anything already waiting was last saved when it was submitted.
        DB::table('job_postings')
            ->where('moderation_status', 'pending')
            ->where('availability_status', '!=', 'draft')
            ->update(['submitted_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn('submitted_at');
        });
    }
};
