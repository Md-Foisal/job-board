<?php

use App\Models\Skill;
use App\Support\SkillMatcher;

test('skills are found as whole words, ignoring case', function () {
    foreach (['Laravel', 'PHP', 'Java', 'JavaScript', 'MySQL'] as $name) {
        Skill::create(['name' => $name]);
    }

    $found = SkillMatcher::inText("Senior LARAVEL developer.\nDaily: php, javascript; some mysqlx scripts.");

    expect(skillNames($found))->toBe(['JavaScript', 'Laravel', 'PHP']);
});

test('names with symbols are found exactly, and never inside a longer name', function (string $text, array $expected) {
    foreach (['C', 'C++', 'C#', '.NET', 'Node.js', 'Node', 'R'] as $name) {
        Skill::create(['name' => $name]);
    }

    expect(skillNames(SkillMatcher::inText($text)))->toBe($expected);
})->with([
    'C++ is not C' => ['Five years of C++ and C#.', ['C#', 'C++']],
    'plain C' => ['Embedded work in C, some Python.', ['C']],
    '.NET on its own' => ['Backend in .NET and SQL Server', ['.NET']],
    'Node.js is not Node' => ['APIs with Node.js.', ['Node.js']],
    'Node.js written other ways' => ['NodeJS, then node-js', ['Node.js']],
    'glued with a hyphen' => ['Objective-C and C-level reporting', []],
    'R&D is not R' => ['Led the R&D team', []],
    'R as a language' => ['Statistics in R and SQL', ['R']],
]);

test('very short names match only as written, so everyday words are not skills', function () {
    Skill::create(['name' => 'Go']);
    Skill::create(['name' => 'UI']);

    expect(skillNames(SkillMatcher::inText('Happy to go the extra mile on ui polish')))->toBe([])
        ->and(skillNames(SkillMatcher::inText('Services in Go; UI in React')))->toBe(['Go', 'UI']);
});

test('multi-word names may be broken across lines or joined with a hyphen', function () {
    Skill::create(['name' => 'Machine Learning']);

    expect(skillNames(SkillMatcher::inText("Research in machine\nlearning")))->toBe(['Machine Learning'])
        ->and(skillNames(SkillMatcher::inText('A machine-learning pipeline')))->toBe(['Machine Learning']);
});

test('a skill merged away or deleted by staff is never returned', function () {
    Skill::create(['name' => 'Laravel']);
    Skill::create(['name' => 'Lumen'])->delete();

    expect(skillNames(SkillMatcher::inText('Laravel and Lumen')))->toBe(['Laravel'])
        ->and(skillNames(SkillMatcher::byNames(['Lumen'])['matched']))->toBe([]);
});

test('an empty text finds nothing', function () {
    Skill::create(['name' => 'PHP']);

    expect(SkillMatcher::inText("  \n "))->toBeEmpty();
});

test('a list of names is matched forgiving case and punctuation, and the rest are reported', function () {
    foreach (['Node.js', 'C', 'C++', 'Vue.js', 'Laravel'] as $name) {
        Skill::create(['name' => $name]);
    }

    $result = SkillMatcher::byNames(['NodeJS', 'c++', ' vue js ', 'Laravel', 'laravel', 'Kubernetes', 'kubernetes', '']);

    expect(skillNames($result['matched']))->toBe(['C++', 'Laravel', 'Node.js', 'Vue.js'])
        ->and($result['matched'])->toHaveCount(4)
        ->and($result['unmatched'])->toBe(['Kubernetes']);
});
