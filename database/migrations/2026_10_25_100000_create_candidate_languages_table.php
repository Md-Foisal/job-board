<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Languages a candidate speaks, each with a level on the European
     * scale (A1 to C2) or "native", the way Europass and LinkedIn record
     * them. One row per language.
     */
    public function up(): void
    {
        Schema::create('candidate_languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('level', 10);
            $table->timestamps();

            $table->unique(['candidate_profile_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_languages');
    }
};
