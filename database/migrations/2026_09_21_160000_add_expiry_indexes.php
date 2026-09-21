<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every public listing asks "open and not past its date", and the expiry
     * commands ask the same thing every minute; both read these two columns.
     */
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->index(['availability_status', 'expires_at']);
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropIndex(['availability_status', 'expires_at']);
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropIndex(['status', 'expires_at']);
        });
    }
};
