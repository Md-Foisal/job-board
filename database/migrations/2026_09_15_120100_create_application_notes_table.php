<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();

            // Nullable on purpose: the note is the company's record of its own
            // hiring decision and outlives whoever typed it, so deleting the
            // author leaves the note standing with an unknown writer.
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('note');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_notes');
    }
};
