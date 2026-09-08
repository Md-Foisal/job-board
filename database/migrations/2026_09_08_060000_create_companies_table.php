<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('identity_type');
            $table->text('description')->nullable();
            $table->string('website_url')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('size')->nullable();
            $table->string('industry')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('account_status')->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
