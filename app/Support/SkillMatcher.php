<?php

namespace App\Support;

use App\Models\Skill;
use Illuminate\Support\Collection;

/**
 * Which of the platform's skills a CV mentions. Only skills on the list
 * are ever returned -- the list is kept by staff, and reading a CV never
 * adds to it. A skill merged into another is soft-deleted, so it is not
 * on the list either.
 *
 * In text, a skill counts only as a whole word. Many skill names carry
 * symbols (C++, C#, .NET, Node.js), so "whole word" is defined by hand
 * rather than with \b: the name must not be glued to a letter or digit on
 * either side, or to + # & (so C is not found in C++ or R&D), or to a
 * hyphen or dot that joins it to another word (so C is not found in
 * Objective-C, nor Node in Node.js). Inside a name, a dot, space or hyphen
 * may be written any of those ways or left out: Node.js also matches
 * NodeJS and Node JS.
 *
 * Case is ignored, except for names of one or two letters (Go, R, C, UI):
 * those match only as written, or "go" in any sentence would be a skill.
 */
final class SkillMatcher
{
    /**
     * @return Collection<int, Skill>
     */
    public static function inText(string $text): Collection
    {
        if (trim($text) === '') {
            return new Collection;
        }

        return Skill::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->filter(fn (Skill $skill) => preg_match(self::pattern($skill->name), $text) === 1)
            ->values();
    }

    /**
     * Skill names given as a list -- by an AI reading the CV, for
     * instance -- matched to the platform's skills, forgiving only in
     * case and punctuation (NodeJS finds Node.js; C++ never finds C).
     * The names left over are returned too, so a page can say which ones
     * are not on the list.
     *
     * @param  iterable<int, string>  $names
     * @return array{matched: Collection<int, Skill>, unmatched: array<int, string>}
     */
    public static function byNames(iterable $names): array
    {
        $skills = Skill::query()->orderBy('name')->get(['id', 'name'])
            ->keyBy(fn (Skill $skill) => self::key($skill->name));

        $matched = new Collection;
        $unmatched = [];

        foreach ($names as $name) {
            $name = trim(preg_replace('/\s+/u', ' ', (string) $name) ?? '');
            $skill = $skills->get(self::key($name));

            if ($skill !== null) {
                $matched->put($skill->id, $skill);
            } elseif ($name !== '' && ! in_array(mb_strtolower($name), array_map('mb_strtolower', $unmatched), true)) {
                $unmatched[] = $name;
            }
        }

        return ['matched' => $matched->values(), 'unmatched' => $unmatched];
    }

    /**
     * The name with case, spaces, dots, hyphens and underscores removed:
     * the parts people write differently. + and # stay, since they change
     * which language is meant.
     */
    private static function key(string $name): string
    {
        return mb_strtolower(preg_replace('/[\s.\-_]+/u', '', $name) ?? '');
    }

    private static function pattern(string $name): string
    {
        $pieces = preg_split('/([\s.\-]+)/u', trim($name), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

        $body = '';

        foreach ($pieces as $index => $piece) {
            $isSeparator = preg_match('/^[\s.\-]+$/u', $piece) === 1;
            $betweenWords = $index > 0 && $index < count($pieces) - 1;

            // A separator inside the name is flexible; one at its edge (.NET) is literal.
            $body .= $isSeparator && $betweenWords ? '[\s.\-]?' : preg_quote($piece, '/');
        }

        $caseSensitive = preg_match('/^\p{L}{1,2}$/u', self::key($name)) === 1;

        return '/(?<![\p{L}\p{N}+#&])(?<![\p{L}\p{N}][.\-])'
            .$body
            .'(?![\p{L}\p{N}+#&])(?![.\-][\p{L}\p{N}])/u'
            .($caseSensitive ? '' : 'i');
    }
}
