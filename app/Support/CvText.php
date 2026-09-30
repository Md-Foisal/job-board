<?php

namespace App\Support;

use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Header;
use Smalot\PdfParser\Parser;
use Smalot\PdfParser\PDFObject;
use Throwable;
use ZipArchive;

/**
 * The plain text of a CV, for the product's own resume reading: no AI,
 * nothing sent anywhere, done in the request.
 *
 * PDF and DOCX are read; the old binary .doc format is not, and neither
 * is a scanned PDF, which holds pictures of text rather than text. Both
 * come back as an empty string, as does a damaged or password-protected
 * file, and the page then asks the candidate to fill their profile in by
 * hand.
 *
 * A CV often hides its links behind words ("LinkedIn", "Portfolio"). The
 * addresses behind those links are added after the text, one per line, so
 * whatever reads the text for links sees them too.
 *
 * Limits keep a hostile or huge file from tying up the request: only the
 * first pages of a PDF are read, decompression has a memory ceiling, an
 * oversized part of a DOCX (a zip bomb) is skipped, and the text itself
 * is capped.
 */
final class CvText
{
    public const MAX_PAGES = 10;

    public const MAX_CHARACTERS = 100_000;

    private const PDF_DECODE_MEMORY_BYTES = 32 * 1024 * 1024;

    private const DOCX_PART_MAX_BYTES = 10 * 1024 * 1024;

    public static function supports(Document $document): bool
    {
        return in_array(self::extension($document), ['pdf', 'docx'], true);
    }

    public static function of(Document $document): string
    {
        $path = Storage::disk('local')->path($document->file_path);

        try {
            $text = match (self::extension($document)) {
                'pdf' => self::fromPdf($path),
                'docx' => self::fromDocx($path),
                default => '',
            };
        } catch (Throwable $exception) {
            Log::warning('Could not read the text of a CV.', [
                'document_id' => $document->id,
                'error' => $exception->getMessage(),
            ]);

            return '';
        }

        return self::tidy($text);
    }

    /**
     * The extension the file was stored under, which Laravel picks from
     * the file's content when it is uploaded -- not the name the candidate
     * gave it.
     */
    private static function extension(Document $document): string
    {
        return strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION));
    }

    private static function fromPdf(string $path): string
    {
        $config = new Config;
        $config->setRetainImageContent(false);
        $config->setDecodeMemoryLimit(self::PDF_DECODE_MEMORY_BYTES);

        $pdf = (new Parser([], $config))->parseFile($path);

        $links = [];

        foreach ($pdf->getObjectsByType('Annot') as $annotation) {
            $action = $annotation->get('A');
            $action = $action instanceof PDFObject ? $action->getHeader() : $action;

            if ($action instanceof Header && $action->has('URI')) {
                $links[] = (string) $action->get('URI')->getContent();
            }
        }

        return self::withLinks($pdf->getText(self::MAX_PAGES), $links);
    }

    /**
     * A DOCX is a zip of XML files. The body, headers and footers are
     * read -- contact details often sit in the header -- and the XML is
     * turned into text by its paragraph and break marks, without an XML
     * parser, so nothing in the file can make the reader fetch anything.
     * Hyperlink addresses live in separate relationship files.
     */
    private static function fromDocx(string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The file is not a readable DOCX.');
        }

        $parts = [];
        $links = [];

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);

                if ($stat === false || $stat['size'] > self::DOCX_PART_MAX_BYTES) {
                    continue;
                }

                if (preg_match('#^word/(document|header\d*|footer\d*)\.xml$#', $stat['name'])) {
                    $parts[$stat['name']] = self::wordXmlToText((string) $zip->getFromIndex($index));
                } elseif (preg_match('#^word/_rels/[^/]+\.rels$#', $stat['name'])) {
                    $links = [...$links, ...self::hyperlinkTargets((string) $zip->getFromIndex($index))];
                }
            }
        } finally {
            $zip->close();
        }

        // Headers first, then the body, then footers: the order a reader sees.
        uksort($parts, fn (string $a, string $b) => self::partOrder($a) <=> self::partOrder($b) ?: strcmp($a, $b));

        return self::withLinks(implode("\n", $parts), $links);
    }

    private static function partOrder(string $name): int
    {
        return match (true) {
            str_starts_with($name, 'word/header') => 0,
            $name === 'word/document.xml' => 1,
            default => 2,
        };
    }

    private static function wordXmlToText(string $xml): string
    {
        $xml = preg_replace(['#</w:p>#', '#<w:(br|cr)\b[^>]*/>#', '#<w:tab\b[^>]*/>#'], ["\n", "\n", "\t"], $xml) ?? '';

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * @return array<int, string>
     */
    private static function hyperlinkTargets(string $xml): array
    {
        preg_match_all('#<Relationship\b[^>]*/>#', $xml, $relationships);

        $targets = [];

        foreach ($relationships[0] as $relationship) {
            if (str_contains($relationship, '/relationships/hyperlink"')
                && preg_match('#\bTarget="([^"]+)"#', $relationship, $target)) {
                $targets[] = html_entity_decode($target[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }

        return $targets;
    }

    /**
     * @param  array<int, string>  $links
     */
    private static function withLinks(string $text, array $links): string
    {
        $unseen = array_filter(
            array_unique(array_map('trim', $links)),
            fn (string $link) => $link !== '' && ! str_contains($text, $link),
        );

        return $unseen === [] ? $text : $text."\n\n".implode("\n", $unseen);
    }

    /**
     * Valid UTF-8 (a pattern with the u flag rejects anything else), no
     * control characters, runs of spaces made single, and at most two line
     * breaks in a row.
     */
    private static function tidy(string $text): string
    {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[^\P{C}\n\t]/u', '', $text) ?? '';
        $text = preg_replace(['/[ \t\x{00A0}]+/u', '/ *\n */', '/\n{3,}/'], [' ', "\n", "\n\n"], $text) ?? '';

        return mb_substr(trim($text), 0, self::MAX_CHARACTERS);
    }
}
