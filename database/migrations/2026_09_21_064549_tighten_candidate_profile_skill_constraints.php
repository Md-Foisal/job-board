<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Brings candidate_profile_skill in line with the physical data model
     * and with job_posting_skill: a skill that people claim cannot be
     * hard-deleted out from under them, so the foreign key restricts
     * instead of cascading.
     *
     * One row per candidate and skill is the table's primary key, set when
     * it is created. Any duplicate pairs from a database built before that
     * are collapsed first, keeping the higher proficiency; on a fresh
     * database there are none.
     */
    public function up(): void
    {
        $rank = ['beginner' => 1, 'intermediate' => 2, 'advanced' => 3];

        DB::table('candidate_profile_skill')
            ->select('candidate_profile_id', 'skill_id')
            ->groupBy('candidate_profile_id', 'skill_id')
            ->havingRaw('count(*) > 1')
            ->get()
            ->each(function ($pair) use ($rank) {
                $rows = DB::table('candidate_profile_skill')
                    ->where('candidate_profile_id', $pair->candidate_profile_id)
                    ->where('skill_id', $pair->skill_id);

                $best = (clone $rows)->pluck('proficiency')
                    ->sortByDesc(fn ($level) => $rank[$level] ?? 0)
                    ->first();

                (clone $rows)->delete();

                DB::table('candidate_profile_skill')->insert([
                    'candidate_profile_id' => $pair->candidate_profile_id,
                    'skill_id' => $pair->skill_id,
                    'proficiency' => $best,
                ]);
            });

        Schema::table('candidate_profile_skill', function (Blueprint $table) {
            $table->dropForeign(['skill_id']);
        });

        Schema::table('candidate_profile_skill', function (Blueprint $table) {
            $table->foreign('skill_id')->references('id')->on('skills')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profile_skill', function (Blueprint $table) {
            $table->dropForeign(['skill_id']);
        });

        Schema::table('candidate_profile_skill', function (Blueprint $table) {
            $table->foreign('skill_id')->references('id')->on('skills')->cascadeOnDelete();
        });
    }
};
