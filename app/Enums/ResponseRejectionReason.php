<?php

namespace App\Enums;

/**
 * The grounds on which staff keep a company's answer to a review off the
 * public page. A closed list, like the one for reviews, so that a
 * response is judged by what it does to the writer and the reader, not
 * by whether staff agree with it.
 *
 * The first two exist because the company holds power the reviewer does
 * not: it knows who it turned down, and it can threaten. A response that
 * points at the writer or tries to scare them would silence the next
 * review as surely as deleting this one.
 */
enum ResponseRejectionReason: string
{
    case IdentifiesWriter = 'identifies_writer';
    case Threatening = 'threatening';
    case Abusive = 'abusive';
    case PersonalInformation = 'personal_information';
    case Discriminatory = 'discriminatory';
    case Unrelated = 'unrelated';

    public function label(): string
    {
        return match ($this) {
            self::IdentifiesWriter => 'Points to who wrote the review',
            self::Threatening => 'Threatens or pressures the writer',
            self::Abusive => 'Defamatory, harassing, abusive or obscene',
            self::PersonalInformation => 'Identifies someone else',
            self::Discriminatory => 'Discriminatory',
            self::Unrelated => 'Not an answer to the review',
        };
    }

    /**
     * What the company is told, written so it can fix the response.
     */
    public function forCompany(): string
    {
        return match ($this) {
            self::IdentifiesWriter => 'It names the writer, or says something about them that could tell readers who they are, such as the role, the dates or what happened to the application. Answer the review, not the person.',
            self::Threatening => 'It threatens the writer, for example with legal action, or pressures them to change or remove the review.',
            self::Abusive => 'It contains language that is defamatory, harassing, abusive or obscene.',
            self::PersonalInformation => 'It names or identifies someone, or includes personal contact details.',
            self::Discriminatory => 'It contains discriminatory content.',
            self::Unrelated => 'It does not answer the review, for example an advert or a link to other jobs.',
        };
    }
}
