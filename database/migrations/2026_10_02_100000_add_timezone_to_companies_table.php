<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The company's own zone, as an IANA name. Its days are the ones the
     * whole team shares: a closing date runs to the end of that day, and
     * the analytics count views and applications by it, so a manager in
     * London and an owner in Dhaka read the same numbers.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('timezone', 64)->default('UTC')->after('industry');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
