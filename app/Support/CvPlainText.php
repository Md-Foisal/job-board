<?php

namespace App\Support;

/**
 * A CV as plain text, for application forms that ask to paste one in:
 * the sections in the CV's order, headings in capitals, bullets as
 * dashes, no layout at all. Built from the Word version's reading of
 * the CV, so the two never say different things.
 */
final class CvPlainText
{
    public static function for(CvData $cv, CvDesign $design): string
    {
        $docx = CvDocx::for($cv, $design);
        $path = tempnam(sys_get_temp_dir(), 'cv-txt');
        file_put_contents($path, $docx);

        $zip = new \ZipArchive;
        $zip->open($path);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($path);

        $lines = [];

        foreach (self::paragraphs($xml) as [$style, $bullet, $text]) {
            if ($style === 'Heading1') {
                $lines[] = '';
                $lines[] = mb_strtoupper($text);
            } elseif ($style === 'EntryTitle') {
                $lines[] = '';
                $lines[] = $text;
            } else {
                $lines[] = ($bullet ? '- ' : '').$text;
            }
        }

        return trim(implode("\n", $lines))."\n";
    }

    /**
     * @return list<array{0: ?string, 1: bool, 2: string}>
     */
    private static function paragraphs(string $xml): array
    {
        preg_match_all('#<w:p>(.*?)</w:p>#s', $xml, $matches);

        return array_map(function (string $paragraph) {
            preg_match('#<w:pStyle w:val="([^"]+)"/>#', $paragraph, $style);
            preg_match_all('#<w:t xml:space="preserve">(.*?)</w:t>#s', $paragraph, $texts);

            return [
                $style[1] ?? null,
                str_contains($paragraph, '<w:numPr>'),
                html_entity_decode(implode('', $texts[1]), ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            ];
        }, $matches[1]);
    }
}
