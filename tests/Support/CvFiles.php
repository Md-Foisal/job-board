<?php

namespace Tests\Support;

use App\Enums\DocumentType;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Real CV files built in code, so a test shows exactly what the file
 * contains instead of pointing at an opaque fixture.
 */
final class CvFiles
{
    /**
     * A real, minimal PDF: one Helvetica text line per entry, one page per
     * inner array, and link annotations on the first page.
     *
     * @param  array<int, array<int, string>>  $pages
     * @param  array<int, string>  $links
     */
    public static function pdf(array $pages, array $links = []): string
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
    public static function docx(array $paragraphs, ?string $header = null, array $hyperlinks = []): string
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

    /**
     * Store the file on the local disk as a CV in someone's library.
     */
    public static function stored(string $extension, string $bytes, array $attributes = []): Document
    {
        $path = 'documents/cv-'.uniqid().'.'.$extension;
        Storage::disk('local')->put($path, $bytes);

        return Document::factory()->create([
            'document_type' => DocumentType::Cv,
            'file_path' => $path,
            'original_filename' => 'Karim Rahman CV.'.$extension,
            ...$attributes,
        ]);
    }
}
