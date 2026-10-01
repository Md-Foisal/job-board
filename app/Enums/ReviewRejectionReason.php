<?php

namespace App\Enums;

/**
 * The only grounds on which staff keep a company review off the public
 * page. A closed list, and the same for every review whatever it says
 * about the company: holding back reviews by what they say rather than
 * by these grounds would turn moderation into suppression. There is
 * deliberately no "too negative".
 *
 * The grounds follow the FTC rule on consumer reviews (16 CFR 465.7(b)),
 * which lists what a business may withhold without suppressing reviews.
 */
enum ReviewRejectionReason: string
{
    case Confidential = 'confidential';
    case Abusive = 'abusive';
    case PersonalInformation = 'personal_information';
    case Discriminatory = 'discriminatory';
    case FalseOrMisleading = 'false_or_misleading';
    case NotGenuine = 'not_genuine';
    case Unrelated = 'unrelated';

    public function label(): string
    {
        return match ($this) {
            self::Confidential => 'Confidential information',
            self::Abusive => 'Defamatory, harassing, abusive or obscene',
            self::PersonalInformation => 'Identifies a person',
            self::Discriminatory => 'Discriminatory',
            self::FalseOrMisleading => 'Clearly false or misleading',
            self::NotGenuine => 'Not a genuine account',
            self::Unrelated => 'Not about the hiring process',
        };
    }

    /**
     * What the writer is told, written so they can fix it.
     */
    public function forWriter(): string
    {
        return match ($this) {
            self::Confidential => 'It shares information the company keeps confidential, such as internal figures or trade secrets.',
            self::Abusive => 'It contains language that is defamatory, harassing, abusive or obscene.',
            self::PersonalInformation => 'It names or identifies someone, or includes contact details. Describe the process, not the people in it.',
            self::Discriminatory => 'It contains discriminatory content.',
            self::FalseOrMisleading => 'It states something that is clearly false or misleading.',
            self::NotGenuine => 'It does not read as a genuine account of your own hiring process.',
            self::Unrelated => 'It is not about the hiring process with this company.',
        };
    }
}
