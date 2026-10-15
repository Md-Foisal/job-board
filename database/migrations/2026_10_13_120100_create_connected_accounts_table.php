<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An outside account (Google, GitHub) a person signs in with.
 *
 * The provider's own id for the account is what recognises them next
 * time, never the email address: an address can change hands or move
 * between accounts, the id cannot. No access tokens are kept -- signing
 * in is all the account is used for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connected_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('provider_user_id');
            $table->string('email')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connected_accounts');
    }
};
