<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Messages sent through the contact form, and staff's answer to each.
     *
     * The sender's name and email are stored as typed, even when they are
     * signed in: that is the address the reply goes to. user_id only says
     * which account sent it, so staff can open it. A message is open until
     * closed_at is set, with a reply (reply_body) or without one.
     */
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email');
            $table->string('topic', 32);
            $table->text('body');
            $table->text('reply_body')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['closed_at', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
