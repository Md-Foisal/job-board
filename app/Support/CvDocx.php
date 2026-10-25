<?php

namespace App\Support;

use App\Enums\CvSection;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use RuntimeException;
use ZipArchive;

/**
 * A CV as a Word file (.docx), for employers and job sites that ask for
 * Word. Always one column of real paragraphs in the CV's section order,
 * whatever template the PDF uses: a Word file is opened and edited, and
 * a recruiter's system reads it like the Classic template.
 *
 * Written directly as Office Open XML (a zip of a few XML parts), so no
 * extra package is needed: a document, its styles, one bullet list
 * definition and the parts that tie them together. Role descriptions
 * arrive as the profile's cleaned HTML (p, ul, ol, li, strong, em, a,
 * h3, h4) and become paragraphs, bold and italic runs and bullets.
 */
final class CvDocx
{
    private string $body = '';

    private function __construct(private readonly CvData $cv, private readonly CvDesign $design, private readonly string $paper) {}

    /**
     * @param  string  $paper  a4 or letter
     */
    public static function for(CvData $cv, CvDesign $design, string $paper = 'a4'): string
    {
        return (new self($cv, $design, $paper))->build();
    }

    private function build(): string
    {
        $cv = $this->cv;

        $this->paragraph([[$cv->name, ['bold' => true, 'size' => 36]]], 'Title');

        if ($cv->headline) {
            $this->paragraph([[$cv->headline, ['size' => 24]]]);
        }

        $contact = collect([$cv->email, $cv->phone, $cv->location])->filter()->implode(' · ');
        if ($contact !== '') {
            $this->paragraph([[$contact, []]]);
        }
        if ($cv->links !== []) {
            $this->paragraph([[collect($cv->links)->pluck('text')->implode(' · '), []]]);
        }

        foreach ($this->design->sections() as $section) {
            $this->section($section);
        }

        if ($cv->qrUrl) {
            $this->paragraph([[$this->design->label('Online version', 'অনলাইন সংস্করণ').': '.$cv->qrUrl, []]]);
        }

        return $this->zip();
    }

    private function section(CvSection $section): void
    {
        $cv = $this->cv;
        $heading = fn () => $this->paragraph([[$this->design->heading($section), []]], 'Heading1');

        switch ($section) {
            case CvSection::Summary:
                if ($cv->summary) {
                    $heading();
                    foreach (preg_split('/\R/u', $cv->summary) ?: [] as $line) {
                        $this->paragraph([[$line, []]]);
                    }
                }
                break;

            case CvSection::Experience:
                if ($cv->experience !== []) {
                    $heading();
                    foreach ($cv->experience as $role) {
                        $this->entry($role['title'], $role['company'], $role['dates']);
                        $this->html((string) $role['description']);
                    }
                }
                break;

            case CvSection::Projects:
                if ($cv->projects !== []) {
                    $heading();
                    foreach ($cv->projects as $project) {
                        $this->entry($project['name'], null, $project['dates']);
                        $this->html((string) $project['description']);
                        if ($project['links'] !== []) {
                            $this->paragraph([[collect($project['links'])->map(fn (array $link) => __($link['label']).': '.$link['text'])->implode(' · '), []]]);
                        }
                    }
                }
                break;

            case CvSection::Education:
                if ($cv->education !== []) {
                    $heading();
                    foreach ($cv->education as $course) {
                        $this->entry($course['title'] !== '' ? $course['title'] : $course['institution'], $course['title'] !== '' ? $course['institution'] : null, $course['dates']);
                    }
                }
                break;

            case CvSection::Skills:
                if ($cv->skills !== []) {
                    $heading();
                    $this->paragraph([[implode(', ', $cv->skills), []]]);
                }
                break;

            case CvSection::Certifications:
                if ($cv->certifications !== []) {
                    $heading();
                    foreach ($cv->certifications as $certification) {
                        $this->entry($certification['name'], $certification['issuer'], $certification['dates']);
                        if ($certification['link']) {
                            $this->paragraph([[$certification['link']['text'], []]]);
                        }
                    }
                }
                break;

            case CvSection::Languages:
                if ($cv->languages !== []) {
                    $heading();
                    $this->paragraph([[collect($cv->languages)->map(fn (array $language) => $language['name'].' ('.($this->design->headings === 'bn' ? $language['level']->bengaliLabel() : __($language['level']->label())).')')->implode(' · '), []]]);
                }
                break;

            case CvSection::Awards:
                if ($cv->awards !== []) {
                    $heading();
                    foreach ($cv->awards as $award) {
                        $this->entry($award['title'], $award['issuer'], $award['date']);
                        $this->lines($award['description']);
                    }
                }
                break;

            case CvSection::Volunteering:
                if ($cv->volunteering !== []) {
                    $heading();
                    foreach ($cv->volunteering as $role) {
                        $this->entry($role['role'], $role['organization'], $role['dates']);
                        $this->lines($role['description']);
                    }
                }
                break;

            case CvSection::Publications:
                if ($cv->publications !== []) {
                    $heading();
                    foreach ($cv->publications as $publication) {
                        $this->entry($publication['title'], $publication['publisher'], $publication['date']);
                        $this->lines($publication['description']);
                        if ($publication['link']) {
                            $this->paragraph([[$publication['link']['text'], []]]);
                        }
                    }
                }
                break;

            case CvSection::Custom:
                foreach ($cv->extras->custom as $custom) {
                    $this->paragraph([[$custom['title'], []]], 'Heading1');
                    foreach ($custom['items'] as $item) {
                        if ($item['title'] !== '') {
                            $this->entry($item['title'], $item['subtitle'] !== '' ? $item['subtitle'] : null, $item['dates'] !== '' ? $item['dates'] : null);
                        }
                        $this->lines($item['description']);
                    }
                }
                break;

            case CvSection::Interests:
                if ($cv->extras->interests !== '') {
                    $heading();
                    $this->paragraph([[$cv->extras->interests, []]]);
                }
                break;

            case CvSection::Personal:
                $rows = $cv->extras->personalRows($this->design->headings);
                if ($rows !== []) {
                    $heading();
                    foreach ($rows as $row) {
                        $this->paragraph([[$row['label'].': ', ['bold' => true]], [$row['value'], []]]);
                    }
                }
                break;

            case CvSection::References:
                if ($cv->extras->referenceMode === 'on_request') {
                    $heading();
                    $this->paragraph([[$this->design->label('Available on request.', 'চাহিবামাত্র দেওয়া হবে।'), []]]);
                } elseif ($cv->extras->referenceMode === 'list' && $cv->extras->references !== []) {
                    $heading();
                    foreach ($cv->extras->references as $person) {
                        $this->paragraph([[$person['name'], ['bold' => true]]]);
                        foreach ([collect([$person['position'], $person['organization']])->filter()->implode(', '), $person['relationship'], collect([$person['email'], $person['phone']])->filter()->implode(' · ')] as $line) {
                            if ($line !== '') {
                                $this->paragraph([[$line, []]]);
                            }
                        }
                    }
                }
                break;

            case CvSection::Declaration:
                if ($cv->extras->declaration) {
                    $heading();
                    $this->paragraph([[$cv->extras->declarationFor($this->design->headings), []]]);
                    $this->paragraph([[' ', []]]);
                    $this->paragraph([[$this->design->label('Signature', 'স্বাক্ষর').' ('.$cv->name.')', []]]);
                    $place = $cv->extras->declarationPlace !== '' ? $this->design->label('Place', 'স্থান').': '.$cv->extras->declarationPlace.' · ' : '';
                    $this->paragraph([[$place.$this->design->label('Date', 'তারিখ').': ____________', []]]);
                }
                break;
        }
    }

    private function entry(string $title, ?string $at, ?string $dates): void
    {
        $runs = [[$title, ['bold' => true]]];

        if ($at !== null && $at !== '') {
            $runs[] = [' · '.$at, []];
        }

        $this->paragraph($runs, 'EntryTitle');

        if ($dates !== null && $dates !== '') {
            $this->paragraph([[$dates, ['color' => '6B7280', 'size' => 18]]]);
        }
    }

    private function lines(?string $text): void
    {
        foreach (preg_split('/\R/u', (string) $text) ?: [] as $line) {
            if (trim($line) !== '') {
                $this->paragraph([[$line, []]]);
            }
        }
    }

    /**
     * The cleaned HTML of a description, as paragraphs and bullets.
     */
    private function html(string $html): void
    {
        if (trim(strip_tags($html)) === '') {
            return;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div>'.$html.'</div>', LIBXML_NONET);
        libxml_use_internal_errors($previous);

        $root = $document->getElementsByTagName('div')->item(0);

        if ($root !== null) {
            $this->blocks($root);
        }
    }

    private function blocks(DOMNode $parent): void
    {
        $loose = [];
        $flush = function () use (&$loose) {
            $runs = array_values(array_filter($loose, fn (array $run) => trim($run[0]) !== ''));
            if ($runs !== []) {
                $this->paragraph($runs);
            }
            $loose = [];
        };

        foreach ($parent->childNodes as $node) {
            $name = $node instanceof DOMElement ? strtolower($node->tagName) : null;

            if (in_array($name, ['p', 'h3', 'h4', 'div'], true)) {
                $flush();
                $runs = $this->runs($node, in_array($name, ['h3', 'h4'], true) ? ['bold' => true] : []);
                if ($runs !== []) {
                    $this->paragraph($runs);
                }
            } elseif (in_array($name, ['ul', 'ol'], true)) {
                $flush();
                foreach ($node->childNodes as $item) {
                    if ($item instanceof DOMElement && strtolower($item->tagName) === 'li') {
                        $runs = $this->runs($item);
                        if ($runs !== []) {
                            $this->paragraph($runs, null, true);
                        }
                    }
                }
            } else {
                $loose = [...$loose, ...$this->runs($node)];
            }
        }

        $flush();
    }

    /**
     * @param  array<string, mixed>  $format
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    private function runs(DOMNode $node, array $format = []): array
    {
        if ($node instanceof DOMText) {
            $text = preg_replace('/\s+/u', ' ', $node->textContent) ?? '';

            return $text === '' ? [] : [[$text, $format]];
        }

        if (! $node instanceof DOMElement) {
            return [];
        }

        $tag = strtolower($node->tagName);
        $format = match ($tag) {
            'strong', 'b' => [...$format, 'bold' => true],
            'em', 'i' => [...$format, 'italic' => true],
            default => $format,
        };

        $runs = [];
        foreach ($node->childNodes as $child) {
            $runs = [...$runs, ...$this->runs($child, $format)];
        }

        if ($tag === 'a') {
            $href = (string) $node->getAttribute('href');
            $label = trim($node->textContent);
            if (preg_match('#^https?://#i', $href) === 1 && $label !== $href) {
                $runs[] = [' ('.$href.')', $format];
            }
        }

        return $runs;
    }

    /**
     * @param  list<array{0: string, 1: array<string, mixed>}>  $runs
     */
    private function paragraph(array $runs, ?string $style = null, bool $bullet = false): void
    {
        $properties = '';

        if ($style !== null) {
            $properties .= '<w:pStyle w:val="'.$style.'"/>';
        }

        if ($bullet) {
            $properties .= '<w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr>';
        }

        $xml = '<w:p>'.($properties !== '' ? '<w:pPr>'.$properties.'</w:pPr>' : '');

        foreach ($runs as [$text, $format]) {
            $rPr = '';
            if ($format['bold'] ?? false) {
                $rPr .= '<w:b/>';
            }
            if ($format['italic'] ?? false) {
                $rPr .= '<w:i/>';
            }
            if (isset($format['color'])) {
                $rPr .= '<w:color w:val="'.$format['color'].'"/>';
            }
            if (isset($format['size'])) {
                $rPr .= '<w:sz w:val="'.$format['size'].'"/>';
            }

            $xml .= '<w:r>'.($rPr !== '' ? '<w:rPr>'.$rPr.'</w:rPr>' : '').'<w:t xml:space="preserve">'.$this->escape($text).'</w:t></w:r>';
        }

        $this->body .= $xml.'</w:p>';
    }

    private function escape(string $text): string
    {
        // XML 1.0 forbids most control characters; drop them rather than
        // write a file Word refuses to open.
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text) ?? '';

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function zip(): string
    {
        $accent = ltrim($this->design->colour(), '#');
        $font = $this->design->typeface()->isSerif() ? 'Georgia' : 'Calibri';
        $path = tempnam(sys_get_temp_dir(), 'cv-docx');

        $zip = new ZipArchive;
        throw_unless($zip->open($path, ZipArchive::OVERWRITE) === true, RuntimeException::class, 'Could not open a temporary file for the Word CV.');

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'<Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'</Relationships>');

        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            .'<dc:title>'.$this->escape($this->cv->name.' – CV').'</dc:title><dc:creator>'.$this->escape($this->cv->name).'</dc:creator>'
            .'</cp:coreProperties>');

        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>'
            .'</Relationships>');

        $zip->addFromString('word/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="'.$font.'" w:hAnsi="'.$font.'" w:cs="'.$font.'"/><w:sz w:val="21"/><w:color w:val="1F2937"/></w:rPr></w:rPrDefault>'
            .'<w:pPrDefault><w:pPr><w:spacing w:after="40" w:line="264" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:rPr><w:color w:val="'.$accent.'"/></w:rPr></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/>'
            .'<w:pPr><w:keepNext/><w:spacing w:before="280" w:after="80"/><w:pBdr><w:bottom w:val="single" w:sz="4" w:space="1" w:color="'.$accent.'"/></w:pBdr><w:outlineLvl w:val="0"/></w:pPr>'
            .'<w:rPr><w:b/><w:caps/><w:color w:val="'.$accent.'"/><w:sz w:val="23"/></w:rPr></w:style>'
            .'<w:style w:type="paragraph" w:styleId="EntryTitle"><w:name w:val="Entry title"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="120"/></w:pPr></w:style>'
            .'</w:styles>');

        $zip->addFromString('word/numbering.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/><w:lvlJc w:val="left"/>'
            .'<w:pPr><w:ind w:left="360" w:hanging="240"/></w:pPr></w:lvl></w:abstractNum>'
            .'<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>'
            .'</w:numbering>');

        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            .$this->body
            .'<w:sectPr>'.($this->paper === 'letter' ? '<w:pgSz w:w="12240" w:h="15840"/>' : '<w:pgSz w:w="11906" w:h="16838"/>').'<w:pgMar w:top="1000" w:right="1080" w:bottom="1000" w:left="1080" w:header="0" w:footer="0" w:gutter="0"/></w:sectPr>'
            .'</w:body></w:document>');

        $zip->close();

        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return $bytes;
    }
}
