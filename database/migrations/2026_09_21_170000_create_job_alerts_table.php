<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('criteria');
            $table->string('frequency');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            // The sweep asks for active alerts of one frequency.
            $table->index(['is_active', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_alerts');
    }
};
