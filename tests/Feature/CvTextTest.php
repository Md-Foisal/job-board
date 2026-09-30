<?php

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Skill;
use App\Support\CvLinks;
use App\Support\CvText;
use App\Support\SkillMatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

/**
 * A real, minimal PDF: one Helvetica text line per entry, one page per
 * inner array, and link annotations on the first page.
 *
 * @param  array<int, array<int, string>>  $pages
 * @param  array<int, string>  $links
 */
function cvPdf(array $pages, array $links = []): string
{
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    $kids = [];
    $next = 4;

    foreach ($pages as $number => $lines) {
        $pageId = $next++;
        $contentId = $next++;
        $kids[] = "{$pageId} 0 R";

        $stream = "BT /F1 11 Tf 72 760 Td 14 TL\n";
        foreach ($lines as $line) {
            $stream .= '('.strtr($line, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']).") Tj T*\n";
        }
        $stream .= 'ET';

        $annotations = [];
        foreach ($number === 0 ? $links : [] as $uri) {
            $id = $next++;
            $objects[$id] = "<< /Type /Annot /Subtype /Link /Rect [72 700 200 712] /A << /S /URI /URI ({$uri}) >> >>";
            $annotations[] = "{$id} 0 R";
        }
        $annots = $annotations ? ' /Annots ['.implode(' ', $annotations).']' : '';

        $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentId} 0 R{$annots} >>";
        $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
    }

    $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $id => $body) {
        $offsets[] = strlen($pdf);
        $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
}

/**
 * A minimal DOCX: the body paragraphs, an optional header paragraph, and
 * hyperlinks stored the way Word stores them, as relationships.
 *
 * @param  array<int, string>  $paragraphs
 * @param  array<int, string>  $hyperlinks
 */
function cvDocx(array $paragraphs, ?string $header = null, array $hyperlinks = []): string
{
    $paragraph = fn (string $text) => '<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($text, ENT_XML1).'</w:t></w:r></w:p>';
    $wordXml = fn (string $root, string $body) => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<w:'.$root.' xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'.$body.'</w:'.$root.'>';

    $relationships = '';
    foreach ($hyperlinks as $index => $target) {
        $relationships .= '<Relationship Id="rId'.($index + 10).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="'.htmlspecialchars($target, ENT_XML1).'" TargetMode="External"/>';
    }

    $path = tempnam(sys_get_temp_dir(), 'cv');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
    $zip->addFromString('word/document.xml', $wordXml('document', '<w:body>'.implode('', array_map($paragraph, $paragraphs)).'</w:body>'));
    if ($header !== null) {
        $zip->addFromString('word/header1.xml', $wordXml('hdr', $paragraph($header)));
    }
    $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        .$relationships.'</Relationships>');
    $zip->close();

    $bytes = file_get_contents($path);
    unlink($path);

    return $bytes;
}

function storedCv(string $extension, string $bytes): Document
{
    $path = 'documents/cv-'.uniqid().'.'.$extension;
    Storage::disk('local')->put($path, $bytes);

    return Document::factory()->create([
        'document_type' => DocumentType::Cv,
        'file_path' => $path,
        'original_filename' => 'Karim Rahman CV.'.$extension,
    ]);
}

test('the text of a PDF CV is read, with the addresses behind its links', function () {
    $document = storedCv('pdf', cvPdf(
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
    $document = storedCv('pdf', cvPdf([['GitHub: https://github.com/karim']], ['https://github.com/karim']));

    expect(CvText::of($document))->toBe('GitHub: https://github.com/karim');
});

test('only the first pages of a long PDF are read', function () {
    $pages = array_map(fn (int $number) => ["Page {$number}"], range(1, CvText::MAX_PAGES + 2));

    $text = CvText::of(storedCv('pdf', cvPdf($pages)));

    expect($text)->toContain('Page '.CvText::MAX_PAGES)
        ->not->toContain('Page '.(CvText::MAX_PAGES + 1));
});

test('a DOCX CV is read header first, with its hyperlinks and without its markup', function () {
    $document = storedCv('docx', cvDocx(
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
    expect(CvText::of(storedCv('pdf', cvPdf([[]]))))->toBe('');
});

test('a damaged or empty file reads as empty and is logged, instead of failing the page', function (string $extension, string $bytes) {
    Log::spy();
    $document = storedCv($extension, $bytes);

    expect(CvText::of($document))->toBe('');

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => $context['document_id'] === $document->id);
})->with([
    'damaged PDF' => ['pdf', 'this is not really a pdf'],
    'damaged DOCX' => ['docx', 'this is not really a docx'],
    'empty PDF' => ['pdf', ''],
    'empty DOCX' => ['docx', ''],
]);

test('an old Word .doc file is not something the reader supports', function () {
    $document = storedCv('doc', 'binary word 97 content');

    expect(CvText::supports($document))->toBeFalse()
        ->and(CvText::of($document))->toBe('');
});

test('the text comes back tidy: no control characters, single spaces, no long runs of blank lines', function () {
    $document = storedCv('docx', cvDocx(["Karim\u{0007}   Rahman", '', '', '', "Laravel\t\tdeveloper  "]));

    expect(CvText::of($document))->toBe("Karim Rahman\n\nLaravel developer");
});

test('an invalid byte in the file does not blank out the rest of the text', function () {
    $path = tempnam(sys_get_temp_dir(), 'cv');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', "<w:document><w:body><w:p><w:r><w:t>Laravel \xC3\x28 developer</w:t></w:r></w:p></w:body></w:document>");
    $zip->close();

    $text = CvText::of(storedCv('docx', file_get_contents($path)));
    unlink($path);

    expect($text)->toBe('Laravel ?( developer');
});

test('a CV\'s skills and profile links are found from the file itself', function (string $extension, string $bytes) {
    foreach (['Laravel', 'PHP', 'Node.js', 'C++', 'C'] as $name) {
        Skill::create(['name' => $name]);
    }

    $text = CvText::of(storedCv($extension, $bytes));

    expect(skillNames(SkillMatcher::inText($text)))->toBe(['C++', 'Laravel', 'Node.js', 'PHP'])
        ->and(CvLinks::from($text))->toBe([
            'linkedin_url' => 'https://www.linkedin.com/in/karim-rahman',
            'github_url' => 'https://github.com/karim',
            'portfolio_url' => 'https://karim.dev',
        ]);
})->with([
    'PDF' => fn () => ['pdf', cvPdf(
        [['Karim Rahman', 'Laravel and PHP developer; tooling in Node.js and C++'], ['github.com/karim']],
        ['https://www.linkedin.com/in/karim-rahman', 'https://karim.dev'],
    )],
    'DOCX' => fn () => ['docx', cvDocx(
        ['Laravel and PHP developer', 'Tooling in NodeJS and C++'],
        header: 'Karim Rahman',
        hyperlinks: ['https://www.linkedin.com/in/karim-rahman', 'https://github.com/karim', 'https://karim.dev'],
    )],
]);
