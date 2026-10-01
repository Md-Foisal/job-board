<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The zone times are shown in for this person, as an IANA name such as
     * "Asia/Dhaka". Stored rather than read from the browser each time
     * because email has no browser to ask.
     *
     * timezone_automatic is whether it follows the browser (and so the
     * person, when they travel) or stays where they set it by hand.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable()->after('avatar');
            $table->boolean('timezone_automatic')->default(true)->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'timezone_automatic']);
        });
    }
};
