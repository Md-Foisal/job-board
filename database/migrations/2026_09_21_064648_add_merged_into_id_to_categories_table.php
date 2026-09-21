<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A category's slug is a public address. When one is merged into
     * another, its address has to keep working and point to where its
     * postings went -- the data design's rule that a changed address
     * forwards to the new one. This records where.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('merged_into_id')->nullable()->after('slug')
                ->constrained('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_id');
        });
    }
};
