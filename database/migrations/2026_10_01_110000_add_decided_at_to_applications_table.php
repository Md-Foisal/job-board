<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the company hired or rejected this applicant. It anchors the
     * short window in which the decision can still be undone and the
     * candidate has not yet been told; undoing it clears the column.
     * A withdrawal is the candidate's own act and leaves it empty.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->timestamp('decided_at')->nullable()->after('stage');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('decided_at');
        });
    }
};
