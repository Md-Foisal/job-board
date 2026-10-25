<?php

namespace App\Support;

use App\Enums\CvFont;
use App\Enums\CvSection;
use App\Enums\CvTemplate;

/**
 * How one CV is laid out, apart from what it says: the template, its
 * colour and typeface, the size of the type, the room between parts and
 * around the page, the language of the headings, and which sections
 * appear in what order. What it says always comes from the profile and
 * the CV's own extras.
 *
 * Sizes and spaces are steps, not free numbers: each template is drawn
 * for its own sizes, and a step scales all of them together, so a CV
 * never ends up with a heading smaller than its text.
 */
final readonly class CvDesign
{
    /**
     * Colours dark enough to read as text on white paper, each at least
     * 4.5:1 against white, and to survive a black-and-white printer.
     */
    public const ACCENTS = [
        'ink' => '#111827',
        'navy' => '#1E3A8A',
        'teal' => '#0F766E',
        'sunset' => '#C2410C',
        'berry' => '#BE185D',
        'violet' => '#6D28D9',
    ];

    /**
     * The contrast a picked colour needs against white: WCAG's 4.5:1 for
     * body text, since headings and links print in it.
     */
    public const MIN_CONTRAST = 4.5;

    public const SIZES = ['small' => 0.92, 'medium' => 1.0, 'large' => 1.08];

    public const SPACINGS = ['compact' => 0.7, 'normal' => 1.0, 'roomy' => 1.3];

    public const MARGINS = ['narrow' => 0.65, 'normal' => 1.0, 'wide' => 1.25];

    public const HEADING_LANGUAGES = ['en', 'bn'];

    /**
     * Skills on one line separated by commas, the way parsers read them
     * most reliably, or as a bulleted list.
     */
    public const SKILL_LAYOUTS = ['line', 'list'];

    public const PHOTO_SHAPES = ['square', 'round'];

    /**
     * Tighter settings tried in turn to fit a CV on one page: less room
     * between parts, then smaller page margins, then smaller type.
     */
    public const FIT_STEPS = [
        ['spacing' => 'compact'],
        ['margins' => 'narrow'],
        ['size' => 'small'],
    ];

    /**
     * @param  list<string>  $order  every CvSection value, once
     * @param  list<string>  $hidden  the ones left out of this CV
     */
    public function __construct(
        public CvTemplate $template = CvTemplate::Classic,
        public string $accent = 'ink',
        public array $order = [],
        public array $hidden = [],
        public ?CvFont $font = null,
        public string $size = 'medium',
        public string $spacing = 'normal',
        public string $margins = 'normal',
        public string $headings = 'en',
        public bool $qr = false,
        public string $skillLayout = 'line',
        public string $photoShape = 'square',
    ) {}

    /**
     * From whatever the browser sent, keeping only what is known: an
     * unknown template, colour, font or step falls back, an unknown
     * section is dropped, and a section missing from the order goes at
     * its end.
     *
     * @param  array<int, mixed>  $order
     * @param  array<int, mixed>  $hidden
     * @param  array<string, mixed>  $type  font, size, spacing, margins, headings, qr, skills, photo_shape
     */
    public static function from(?string $template, ?string $accent, array $order = [], array $hidden = [], array $type = []): self
    {
        $known = CvSection::defaultOrder();
        $order = array_values(array_unique(array_filter($order, fn ($section) => in_array($section, $known, true))));

        return new self(
            template: CvTemplate::tryFrom((string) $template) ?? CvTemplate::Classic,
            accent: self::knownAccent((string) $accent) ?? 'ink',
            order: [...$order, ...array_values(array_diff($known, $order))],
            hidden: array_values(array_intersect($known, $hidden)),
            font: CvFont::tryFrom((string) ($type['font'] ?? '')),
            size: self::step($type['size'] ?? null, self::SIZES, 'medium'),
            spacing: self::step($type['spacing'] ?? null, self::SPACINGS, 'normal'),
            margins: self::step($type['margins'] ?? null, self::MARGINS, 'normal'),
            headings: in_array($type['headings'] ?? null, self::HEADING_LANGUAGES, true) ? $type['headings'] : 'en',
            qr: (bool) ($type['qr'] ?? false),
            skillLayout: in_array($type['skills'] ?? null, self::SKILL_LAYOUTS, true) ? $type['skills'] : 'line',
            photoShape: in_array($type['photo_shape'] ?? null, self::PHOTO_SHAPES, true) ? $type['photo_shape'] : 'square',
        );
    }

    /**
     * From what a saved CV keeps.
     *
     * @param  array<string, mixed>  $saved
     */
    public static function fromArray(array $saved): self
    {
        return self::from($saved['template'] ?? null, $saved['accent'] ?? null, $saved['order'] ?? [], $saved['hidden'] ?? [], $saved);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'template' => $this->template->value,
            'accent' => $this->accent,
            'order' => $this->order,
            'hidden' => $this->hidden,
            'font' => $this->font?->value,
            'size' => $this->size,
            'spacing' => $this->spacing,
            'margins' => $this->margins,
            'headings' => $this->headings,
            'qr' => $this->qr,
            'skills' => $this->skillLayout,
            'photo_shape' => $this->photoShape,
        ];
    }

    /**
     * The same design with some settings changed.
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return self::fromArray([...$this->toArray(), ...$changes]);
    }

    /**
     * The sections to print, in order.
     *
     * @return list<CvSection>
     */
    public function sections(): array
    {
        $order = $this->order === [] ? CvSection::defaultOrder() : $this->order;

        return array_values(array_map(
            fn (string $section) => CvSection::from($section),
            array_filter($order, fn (string $section) => ! in_array($section, $this->hidden, true)),
        ));
    }

    public function shows(CvSection $section): bool
    {
        return ! in_array($section->value, $this->hidden, true);
    }

    /**
     * The colour the template prints with; Classic is always black.
     */
    public function colour(): string
    {
        if (! $this->template->usesAccent()) {
            return self::ACCENTS['ink'];
        }

        return self::ACCENTS[$this->accent] ?? strtoupper($this->accent);
    }

    /**
     * The colour mixed mostly with white, for the side panel of a
     * template.
     */
    public function tint(float $amount = 0.92): string
    {
        return self::mixWithWhite($this->colour(), $amount);
    }

    /**
     * The typeface in use: the one picked, or the template's own.
     */
    public function typeface(): CvFont
    {
        return $this->font ?? $this->template->font();
    }

    /**
     * A template's type size at this CV's size step, e.g. "10.8pt".
     */
    public function pt(float $size): string
    {
        return round($size * self::SIZES[$this->size], 2).'pt';
    }

    /**
     * A template's space between parts at this CV's spacing step, e.g.
     * "4.2mm".
     */
    public function gap(float $millimetres): string
    {
        return round($millimetres * self::SPACINGS[$this->spacing], 2).'mm';
    }

    /**
     * A template's page margins ("16mm 18mm") at this CV's margin step.
     */
    public function page(string $margins): string
    {
        $factor = self::MARGINS[$this->margins];

        return collect(explode(' ', trim($margins)))
            ->map(fn (string $part) => round((float) $part * $factor, 2).'mm')
            ->implode(' ');
    }

    /**
     * The page margins in millimetres, top/right/bottom/left, for the
     * preview's page-break lines.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public function pageMargins(string $margins): array
    {
        $parts = array_map('floatval', explode(' ', $this->page($margins)));

        return match (count($parts)) {
            1 => [$parts[0], $parts[0], $parts[0], $parts[0]],
            2 => [$parts[0], $parts[1], $parts[0], $parts[1]],
            3 => [$parts[0], $parts[1], $parts[2], $parts[1]],
            default => [$parts[0], $parts[1], $parts[2], $parts[3]],
        };
    }

    /**
     * A section's heading in the CV's heading language.
     */
    public function heading(CvSection $section): string
    {
        return $this->headings === 'bn' ? $section->bengaliHeading() : __($section->heading());
    }

    /**
     * A fixed label ("Contact", "Present") in the CV's heading language.
     */
    public function label(string $english, string $bengali): string
    {
        return $this->headings === 'bn' ? $bengali : __($english);
    }

    /**
     * The tighter design that fits more on a page, or null when there is
     * no tighter step left.
     */
    public function tighter(): ?self
    {
        foreach (self::FIT_STEPS as $step) {
            $key = array_key_first($step);

            if ($this->{$key} !== $step[$key]) {
                return $this->with($step);
            }
        }

        return null;
    }

    /**
     * A named colour, or a hex colour dark enough to read on white.
     */
    public static function knownAccent(string $accent): ?string
    {
        if (array_key_exists($accent, self::ACCENTS)) {
            return $accent;
        }

        if (preg_match('/^#[0-9a-f]{6}$/i', $accent) === 1 && self::contrastWithWhite($accent) >= self::MIN_CONTRAST) {
            return strtoupper($accent);
        }

        return null;
    }

    /**
     * WCAG contrast ratio of a hex colour against white.
     */
    public static function contrastWithWhite(string $hex): float
    {
        $luminance = collect(str_split(ltrim($hex, '#'), 2))
            ->map(fn (string $channel) => hexdec($channel) / 255)
            ->map(fn (float $value) => $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4)
            ->pipe(fn ($channels) => 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2]);

        return round(1.05 / ($luminance + 0.05), 2);
    }

    /**
     * A hex colour mixed with white by the given share, returned as hex,
     * since the PDF renderer reads no colour functions.
     */
    public static function mixWithWhite(string $hex, float $amount): string
    {
        return '#'.collect(str_split(ltrim($hex, '#'), 2))
            ->map(fn (string $channel) => str_pad(dechex((int) round(hexdec($channel) + (255 - hexdec($channel)) * $amount)), 2, '0', STR_PAD_LEFT))
            ->implode('');
    }

    /**
     * @param  array<string, float>  $steps
     */
    private static function step(mixed $value, array $steps, string $default): string
    {
        return is_string($value) && array_key_exists($value, $steps) ? $value : $default;
    }
}
