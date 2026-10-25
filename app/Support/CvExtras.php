<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * The parts of one CV that belong on a CV but not on the profile a
 * company reads: interests, references, personal details, a declaration,
 * and sections of the candidate's own. They live with the saved CV.
 *
 * Personal details are what Bangladeshi and other South Asian CVs
 * customarily carry (and Europass partly), each optional and empty until
 * filled. No identity number is offered at all -- an NID or passport
 * number on a document that is sent around is an identity-theft risk,
 * and employers check identity when they hire, not from the CV.
 *
 * Whatever arrives is cut to the known keys and lengths, so a value
 * saved by any path is safe to print.
 */
final readonly class CvExtras
{
    public const PERSONAL_FIELDS = [
        'date_of_birth' => ['Date of birth', 'জন্ম তারিখ'],
        'nationality' => ['Nationality', 'জাতীয়তা'],
        'gender' => ['Gender', 'লিঙ্গ'],
        'marital_status' => ['Marital status', 'বৈবাহিক অবস্থা'],
        'religion' => ['Religion', 'ধর্ম'],
        'father_name' => ["Father's name", 'পিতার নাম'],
        'mother_name' => ["Mother's name", 'মাতার নাম'],
        'permanent_address' => ['Permanent address', 'স্থায়ী ঠিকানা'],
    ];

    public const REFERENCE_MODES = ['none', 'on_request', 'list'];

    public const MAX_REFERENCES = 3;

    public const MAX_CUSTOM_SECTIONS = 5;

    public const MAX_CUSTOM_ITEMS = 10;

    public const DECLARATION_EN = 'I hereby declare that the information given above is true and correct to the best of my knowledge and belief.';

    public const DECLARATION_BN = 'আমি এই মর্মে ঘোষণা করছি যে উপরে দেওয়া সকল তথ্য আমার জানা ও বিশ্বাস মতে সত্য ও সঠিক।';

    /**
     * @param  array<string, string>  $personal
     * @param  list<array{name: string, position: string, organization: string, email: string, phone: string, relationship: string}>  $references
     * @param  list<array{title: string, items: list<array{title: string, subtitle: string, dates: string, description: string}>}>  $custom
     */
    public function __construct(
        public string $interests = '',
        public string $referenceMode = 'none',
        public array $references = [],
        public array $personal = [],
        public bool $declaration = false,
        public string $declarationText = '',
        public string $declarationPlace = '',
        public array $custom = [],
    ) {}

    /**
     * @param  array<string, mixed>|null  $saved
     */
    public static function fromArray(?array $saved): self
    {
        $saved ??= [];
        $text = fn (mixed $value, int $max) => mb_substr(trim(is_scalar($value) ? (string) $value : ''), 0, $max);

        $personal = [];
        foreach (array_keys(self::PERSONAL_FIELDS) as $key) {
            $value = $text($saved['personal'][$key] ?? '', $key === 'permanent_address' ? 255 : 100);

            if ($value !== '') {
                $personal[$key] = $value;
            }
        }

        $references = collect(is_array($saved['references'] ?? null) ? $saved['references'] : [])
            ->filter(fn ($person) => is_array($person))
            ->map(fn (array $person) => [
                'name' => $text($person['name'] ?? '', 100),
                'position' => $text($person['position'] ?? '', 100),
                'organization' => $text($person['organization'] ?? '', 150),
                'email' => $text($person['email'] ?? '', 150),
                'phone' => $text($person['phone'] ?? '', 30),
                'relationship' => $text($person['relationship'] ?? '', 100),
            ])
            ->filter(fn (array $person) => $person['name'] !== '')
            ->take(self::MAX_REFERENCES)
            ->values()
            ->all();

        $custom = collect(is_array($saved['custom'] ?? null) ? $saved['custom'] : [])
            ->filter(fn ($section) => is_array($section))
            ->map(fn (array $section) => [
                'title' => $text($section['title'] ?? '', 60),
                'items' => collect(is_array($section['items'] ?? null) ? $section['items'] : [])
                    ->filter(fn ($item) => is_array($item))
                    ->map(fn (array $item) => [
                        'title' => $text($item['title'] ?? '', 150),
                        'subtitle' => $text($item['subtitle'] ?? '', 150),
                        'dates' => $text($item['dates'] ?? '', 60),
                        'description' => $text($item['description'] ?? '', 1000),
                    ])
                    ->filter(fn (array $item) => $item['title'] !== '' || $item['description'] !== '')
                    ->take(self::MAX_CUSTOM_ITEMS)
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $section) => $section['title'] !== '')
            ->take(self::MAX_CUSTOM_SECTIONS)
            ->values()
            ->all();

        $mode = in_array($saved['reference_mode'] ?? null, self::REFERENCE_MODES, true) ? $saved['reference_mode'] : 'none';

        return new self(
            interests: $text($saved['interests'] ?? '', 300),
            referenceMode: $mode,
            references: $references,
            personal: $personal,
            declaration: (bool) ($saved['declaration'] ?? false),
            declarationText: $text($saved['declaration_text'] ?? '', 500),
            declarationPlace: $text($saved['declaration_place'] ?? '', 100),
            custom: $custom,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'interests' => $this->interests,
            'reference_mode' => $this->referenceMode,
            'references' => $this->references,
            'personal' => $this->personal,
            'declaration' => $this->declaration,
            'declaration_text' => $this->declarationText,
            'declaration_place' => $this->declarationPlace,
            'custom' => $this->custom,
        ];
    }

    /**
     * The personal details to print, labelled in the CV's heading
     * language, a date of birth written out ("12 March 1999").
     *
     * @return list<array{label: string, value: string}>
     */
    public function personalRows(string $language = 'en'): array
    {
        return collect($this->personal)
            ->map(fn (string $value, string $key) => [
                'label' => $language === 'bn' ? self::PERSONAL_FIELDS[$key][1] : __(self::PERSONAL_FIELDS[$key][0]),
                'value' => $key === 'date_of_birth' ? self::longDate($value) : $value,
            ])
            ->values()
            ->all();
    }

    public function declarationFor(string $language): string
    {
        if ($this->declarationText !== '') {
            return $this->declarationText;
        }

        return $language === 'bn' ? self::DECLARATION_BN : __(self::DECLARATION_EN);
    }

    /**
     * Personal details and references are contact details of a kind: a
     * public web CV leaves them out unless the candidate allows it.
     */
    public function withoutContactDetails(): self
    {
        return new self(
            interests: $this->interests,
            referenceMode: $this->referenceMode === 'list' ? 'on_request' : $this->referenceMode,
            references: [],
            personal: [],
            declaration: $this->declaration,
            declarationText: $this->declarationText,
            declarationPlace: $this->declarationPlace,
            custom: $this->custom,
        );
    }

    private static function longDate(string $value): string
    {
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value)->format('j F Y');
        } catch (Throwable) {
            return $value;
        }
    }
}
