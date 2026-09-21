<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A permanent record of what a staff member did, to what, and why.
     *
     * The subject is polymorphic because one moderation decision can land
     * on a job posting, a company or a user. It is the subject -- not the
     * report -- that an action targets: resolving five reports about the
     * same posting writes one event, not five.
     *
     * admin_id is nullable and SET NULL on delete, for the same reason
     * application_events.changed_by_id is: the trail outlives the account
     * that made it. There is no updated_at, and nothing edits or deletes
     * these rows -- an audit log you can rewrite is not an audit log.
     */
    public function up(): void
    {
        Schema::create('moderation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->morphs('subject');
            $table->string('action');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_events');
    }
};
