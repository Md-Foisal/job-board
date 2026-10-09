<?php

namespace App\Support;

use App\Enums\CvSection;
use App\Enums\CvTemplate;

/**
 * How one CV is laid out, apart from what it says: the template, its
 * colour, and which sections appear in what order. What it says always
 * comes from the profile.
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
     * @param  list<string>  $order  every CvSection value, once
     * @param  list<string>  $hidden  the ones left out of this CV
     */
    public function __construct(
        public CvTemplate $template = CvTemplate::Classic,
        public string $accent = 'ink',
        public array $order = [],
        public array $hidden = [],
    ) {}

    /**
     * From whatever the browser sent, keeping only what is known: an
     * unknown template or colour falls back, an unknown section is
     * dropped, and a section missing from the order goes at its end.
     *
     * @param  array<int, mixed>  $order
     * @param  array<int, mixed>  $hidden
     */
    public static function from(?string $template, ?string $accent, array $order = [], array $hidden = []): self
    {
        $known = CvSection::defaultOrder();
        $order = array_values(array_unique(array_filter($order, fn ($section) => in_array($section, $known, true))));

        return new self(
            template: CvTemplate::tryFrom((string) $template) ?? CvTemplate::Classic,
            accent: array_key_exists((string) $accent, self::ACCENTS) ? $accent : 'ink',
            order: [...$order, ...array_values(array_diff($known, $order))],
            hidden: array_values(array_intersect($known, $hidden)),
        );
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

    /**
     * The colour the template prints with; Classic is always black.
     */
    public function colour(): string
    {
        return $this->template->usesAccent() ? self::ACCENTS[$this->accent] : self::ACCENTS['ink'];
    }

    /**
     * The colour mixed mostly with white, for the side panel of a
     * template: a hex value, since the PDF renderer reads no colour
     * functions.
     */
    public function tint(float $amount = 0.92): string
    {
        $hex = ltrim($this->colour(), '#');

        return '#'.collect(str_split($hex, 2))
            ->map(fn (string $channel) => str_pad(dechex((int) round(hexdec($channel) + (255 - hexdec($channel)) * $amount)), 2, '0', STR_PAD_LEFT))
            ->implode('');
    }
}
