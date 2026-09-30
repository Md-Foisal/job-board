<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per successful AI call: who ran it, which feature, which
     * model, and what it cost. A failed call writes nothing, so it never
     * uses up anyone's monthly allowance.
     *
     * Rows hold counts only -- never the text sent or the answer received --
     * so there is nothing personal here to erase when an account is.
     *
     * company_id is set only when the company's plan pays (a job post
     * review); the monthly count for those is taken per company, so a team
     * shares one allowance. cost_micro_usd is whole millionths of a dollar,
     * which keeps money out of floating point; it is null when the model's
     * price is not configured, because an unknown cost is not a zero one.
     */
    public function up(): void
    {
        Schema::create('ai_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('feature');
            $table->string('provider');
            $table->string('model');
            $table->unsignedInteger('input_tokens');
            $table->unsignedInteger('output_tokens');
            $table->unsignedInteger('cost_micro_usd')->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'feature', 'created_at']);
            $table->index(['company_id', 'feature', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usages');
    }
};
