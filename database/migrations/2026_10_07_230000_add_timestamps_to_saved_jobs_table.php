<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a job was saved, so the saved list can put the latest first.
     * Rows saved before this have no time and are listed after the rest.
     */
    public function up(): void
    {
        Schema::table('saved_jobs', function (Blueprint $table) {
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('saved_jobs', function (Blueprint $table) {
            $table->dropTimestamps();
        });
    }
};
