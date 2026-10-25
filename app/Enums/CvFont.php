<?php

namespace App\Enums;

/**
 * The typefaces a built CV can be set in. Each one is shipped with the
 * app (resources/fonts/cv, SIL Open Font License), so nothing is fetched
 * from a font service when a CV is drawn, and every one has a Bengali
 * partner that takes over for Bengali letters.
 *
 * The browser engine (the preview, and PDFs drawn by Chromium) uses the
 * font itself. DOMPDF, the fallback engine, has only its bundled DejaVu
 * faces, so the same stack ends in DejaVu there.
 */
enum CvFont: string
{
    case Inter = 'inter';
    case Lato = 'lato';
    case SourceSans = 'source-sans-3';
    case NotoSans = 'noto-sans';
    case Merriweather = 'merriweather';
    case SourceSerif = 'source-serif-4';
    case EbGaramond = 'eb-garamond';

    /**
     * Which Unicode ranges each shipped file covers, so a browser only
     * downloads the files a CV's letters need.
     */
    public const RANGES = [
        'latin' => 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD',
        'latin-ext' => 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF',
        'bengali' => 'U+0951-0952,U+0964-0965,U+0980-09FE,U+1CD0,U+1CD2,U+1CD5-1CD6,U+1CD8,U+1CE1,U+1CEA,U+1CED,U+1CF2,U+1CF5-1CF7,U+200C-200D,U+20B9,U+25CC,U+A8F1',
    ];

    public const WEIGHTS = ['400', '700'];

    public function label(): string
    {
        return match ($this) {
            self::Inter => 'Inter',
            self::Lato => 'Lato',
            self::SourceSans => 'Source Sans 3',
            self::NotoSans => 'Noto Sans',
            self::Merriweather => 'Merriweather',
            self::SourceSerif => 'Source Serif 4',
            self::EbGaramond => 'EB Garamond',
        };
    }

    public function isSerif(): bool
    {
        return in_array($this, [self::Merriweather, self::SourceSerif, self::EbGaramond], true);
    }

    /**
     * The Bengali face drawn beside it: a serif one beside a serif.
     */
    public function bengaliPartner(): string
    {
        return $this->isSerif() ? 'noto-serif-bengali' : 'noto-sans-bengali';
    }

    /**
     * The CSS font-family value: the face, its Bengali partner, then the
     * DejaVu face DOMPDF draws with, then the generic family.
     */
    public function stack(): string
    {
        return $this->isSerif()
            ? "'{$this->label()}', 'Noto Serif Bengali', 'DejaVu Serif', serif"
            : "'{$this->label()}', 'Noto Sans Bengali', 'DejaVu Sans', sans-serif";
    }

    /**
     * Every font file of this face and its Bengali partner, as family,
     * subset, weight and file name.
     *
     * @return list<array{family: string, subset: string, weight: string, file: string}>
     */
    public function files(): array
    {
        $files = [];

        foreach (['latin', 'latin-ext'] as $subset) {
            foreach (self::WEIGHTS as $weight) {
                $files[] = ['family' => $this->label(), 'subset' => $subset, 'weight' => $weight, 'file' => "{$this->value}-{$subset}-{$weight}.woff2"];
            }
        }

        $partner = $this->bengaliPartner();
        $partnerName = $this->isSerif() ? 'Noto Serif Bengali' : 'Noto Sans Bengali';

        foreach (self::WEIGHTS as $weight) {
            $files[] = ['family' => $partnerName, 'subset' => 'bengali', 'weight' => $weight, 'file' => "{$partner}-bengali-{$weight}.woff2"];
        }

        return $files;
    }

    /**
     * Whether a file name is one of the shipped fonts, so the font route
     * serves nothing else.
     */
    public static function ships(string $file): bool
    {
        return collect(self::cases())
            ->flatMap(fn (self $font) => array_column($font->files(), 'file'))
            ->contains($file);
    }

    public static function path(string $file): string
    {
        return resource_path('fonts/cv/'.$file);
    }
}
