<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_preferences', function (Blueprint $table) {
            $table->char('location_country', 2)->nullable()->after('preferred_employment_type');
            $table->string('location_city')->nullable()->after('location_country');
            $table->boolean('open_to_relocation')->default(false)->after('location_city');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_preferences', function (Blueprint $table) {
            $table->dropColumn(['location_country', 'location_city', 'open_to_relocation']);
        });
    }
};
