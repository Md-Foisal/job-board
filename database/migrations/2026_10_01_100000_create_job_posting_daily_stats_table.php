<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How many times a posting was viewed, one row per posting per day.
     *
     * A count rather than a log of individual views: the employer's
     * question is "how many people looked, and when", which never needs to
     * know who any one viewer was. So there is no user id, IP address or
     * session id here, and nothing to erase when an account is.
     *
     * The date is the calendar day in the posting company's own time
     * zone, the day its analytics are read in. The unique pair is what
     * lets every view after the first in a day become an increment of the
     * same row instead of a new one.
     */
    public function up(): void
    {
        Schema::create('job_posting_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('views')->default(0);

            $table->unique(['job_posting_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_posting_daily_stats');
    }
};
