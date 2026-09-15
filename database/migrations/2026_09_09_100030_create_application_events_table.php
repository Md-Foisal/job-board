<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * changed_by_id is nullable and SET NULL on delete: the event itself
     * (a stage or outcome transition) is a permanent audit record even if
     * the staff member who made it later leaves. Exactly one of the two
     * column pairs is filled per row -- which axis changed is read from
     * which pair isn't null.
     */
    public function up(): void
    {
        Schema::create('application_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_stage')->nullable();
            $table->string('to_stage')->nullable();
            $table->string('from_outcome_status')->nullable();
            $table->string('to_outcome_status')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_events');
    }
};
