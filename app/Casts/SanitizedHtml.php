<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Mews\Purifier\Facades\Purifier;

/**
 * Cleans rich text on the way in, wherever it comes from.
 *
 * Sanitizing in a controller works right up until the day a second path
 * writes the same column -- an import, a console command, a duplicated
 * posting -- and nobody remembers. Putting it on the attribute means the
 * rule holds for every writer by construction, which is what "never
 * stored unsanitized" has to mean to be worth stating.
 *
 * Reading is a pass-through: what is in the column was already cleaned,
 * and cleaning again on every render would cost time for nothing.
 */
class SanitizedHtml implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = Purifier::clean($value, 'richtext');

        // An editor left untouched still serialises an empty paragraph, and
        // storing that would make a required field look filled in. It
        // collapses to an empty string rather than to null, because some of
        // these columns are NOT NULL: emptiness is the same either way to
        // every reader, but inventing a null here would turn "you left it
        // blank" into a database error instead of a validation message.
        return blank(strip_tags($clean)) ? '' : $clean;
    }
}
