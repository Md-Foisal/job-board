<?php

use App\Models\Skill;
use App\Support\CvLinks;
use App\Support\CvText;
use App\Support\SkillMatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CvFiles;

beforeEach(function () {
    Storage::fake('local');
});

test('the text of a PDF CV is read, with the addresses behind its links', function () {
    $document = CvFiles::stored('pdf', CvFiles::pdf(
        [['Karim Rahman', 'Laravel and Node.js developer'], ['Education: BSc, 2019']],
        ['https://www.linkedin.com/in/karim-rahman', 'https://karim.dev'],
    ));

    expect(CvText::supports($document))->toBeTrue()
        ->and(CvText::of($document))->toBe(
            "Karim Rahman\nLaravel and Node.js developer\n\nEducation: BSc, 2019\n\n"
            ."https://www.linkedin.com/in/karim-rahman\nhttps://karim.dev"
        );
});

test('a link already written out in the text is not added twice', function () {
    $document = CvFiles::stored('pdf', CvFiles::pdf([['GitHub: https://github.com/karim']], ['https://github.com/karim']));

    expect(CvText::of($document))->toBe('GitHub: https://github.com/karim');
});

test('only the first pages of a long PDF are read', function () {
    $pages = array_map(fn (int $number) => ["Page {$number}"], range(1, CvText::MAX_PAGES + 2));

    $text = CvText::of(CvFiles::stored('pdf', CvFiles::pdf($pages)));

    expect($text)->toContain('Page '.CvText::MAX_PAGES)
        ->not->toContain('Page '.(CvText::MAX_PAGES + 1));
});

test('a DOCX CV is read header first, with its hyperlinks and without its markup', function () {
    $document = CvFiles::stored('docx', CvFiles::docx(
        ['Senior developer at Acme & Co', 'Skills: PHP, C++'],
        header: 'Karim Rahman · Dhaka',
        hyperlinks: ['https://github.com/karim', 'https://www.linkedin.com/in/karim-rahman'],
    ));

    expect(CvText::supports($document))->toBeTrue()
        ->and(CvText::of($document))->toBe(
            "Karim Rahman · Dhaka\n\nSenior developer at Acme & Co\nSkills: PHP, C++\n\n"
            ."https://github.com/karim\nhttps://www.linkedin.com/in/karim-rahman"
        );
});

test('a scanned PDF with no text in it reads as empty', function () {
    expect(CvText::of(CvFiles::stored('pdf', CvFiles::pdf([[]]))))->toBe('');
});

test('a damaged or empty file reads as empty and is logged, instead of failing the page', function (string $extension, string $bytes) {
    Log::spy();
    $document = CvFiles::stored($extension, $bytes);

    expect(CvText::of($document))->toBe('');

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => $context['document_id'] === $document->id);
})->with([
    'damaged PDF' => ['pdf', 'this is not really a pdf'],
    'damaged DOCX' => ['docx', 'this is not really a docx'],
    'empty PDF' => ['pdf', ''],
    'empty DOCX' => ['docx', ''],
]);

test('an old Word .doc file is not something the reader supports', function () {
    $document = CvFiles::stored('doc', 'binary word 97 content');

    expect(CvText::supports($document))->toBeFalse()
        ->and(CvText::of($document))->toBe('');
});

test('the text comes back tidy: no control characters, single spaces, no long runs of blank lines', function () {
    $document = CvFiles::stored('docx', CvFiles::docx(["Karim\u{0007}   Rahman", '', '', '', "Laravel\t\tdeveloper  "]));

    expect(CvText::of($document))->toBe("Karim Rahman\n\nLaravel developer");
});

test('an invalid byte in the file does not blank out the rest of the text', function () {
    $path = tempnam(sys_get_temp_dir(), 'cv');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', "<w:document><w:body><w:p><w:r><w:t>Laravel \xC3\x28 developer</w:t></w:r></w:p></w:body></w:document>");
    $zip->close();

    $text = CvText::of(CvFiles::stored('docx', file_get_contents($path)));
    unlink($path);

    expect($text)->toBe('Laravel ?( developer');
});

test('a CV\'s skills and profile links are found from the file itself', function (string $extension, string $bytes) {
    foreach (['Laravel', 'PHP', 'Node.js', 'C++', 'C'] as $name) {
        Skill::create(['name' => $name]);
    }

    $text = CvText::of(CvFiles::stored($extension, $bytes));

    expect(skillNames(SkillMatcher::inText($text)))->toBe(['C++', 'Laravel', 'Node.js', 'PHP'])
        ->and(CvLinks::from($text))->toBe([
            'linkedin_url' => 'https://www.linkedin.com/in/karim-rahman',
            'github_url' => 'https://github.com/karim',
            'portfolio_url' => 'https://karim.dev',
        ]);
})->with([
    'PDF' => fn () => ['pdf', CvFiles::pdf(
        [['Karim Rahman', 'Laravel and PHP developer; tooling in Node.js and C++'], ['github.com/karim']],
        ['https://www.linkedin.com/in/karim-rahman', 'https://karim.dev'],
    )],
    'DOCX' => fn () => ['docx', CvFiles::docx(
        ['Laravel and PHP developer', 'Tooling in NodeJS and C++'],
        header: 'Karim Rahman',
        hyperlinks: ['https://www.linkedin.com/in/karim-rahman', 'https://github.com/karim', 'https://karim.dev'],
    )],
]);
