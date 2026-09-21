<?php

namespace App\Actions;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Folding a duplicate category into another. Every posting filed under
 * the duplicate is refiled under the one it is merged into before the
 * duplicate is removed, so nothing drops out of browsing.
 */
class MergeCategory
{
    public function __invoke(Category $from, Category $into): void
    {
        if ($from->is($into) || $into->trashed()) {
            throw new InvalidArgumentException('A category can only be merged into a different category that is still in use.');
        }

        DB::transaction(function () use ($from, $into) {
            $alreadyFiled = DB::table('category_job_posting')
                ->where('category_id', $into->id)
                ->pluck('job_posting_id');

            DB::table('category_job_posting')
                ->where('category_id', $from->id)
                ->whereIn('job_posting_id', $alreadyFiled)
                ->delete();

            DB::table('category_job_posting')
                ->where('category_id', $from->id)
                ->update(['category_id' => $into->id]);

            // Anything that was already forwarding to $from now forwards
            // straight to $into, so an old address is always one hop away.
            Category::withTrashed()
                ->where('merged_into_id', $from->id)
                ->update(['merged_into_id' => $into->id]);

            $from->merged_into_id = $into->id;
            $from->save();
            $from->delete();
        });
    }
}
