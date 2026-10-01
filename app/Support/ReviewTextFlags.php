<?php

namespace App\Support;

/**
 * Things in a review's text that usually mean it cannot be published as
 * written: an email address, a phone number, or a link. Each one either
 * identifies someone or points away from the review, and the review
 * guidelines staff apply rule all three out.
 *
 * These are flags, never filters: the text is kept exactly as written,
 * the writer is told before staff look, and staff decide. A false alarm
 * costs a second look; silently editing someone's review would cost
 * their trust.
 */
final class ReviewTextFlags
{
    public const EMAIL = 'email';

    public const PHONE = 'phone';

    public const LINK = 'link';

    /**
     * Nine or more digits in one run counts as a phone number, which a
     * salary such as 50,000 or a year range like 2019-2023 is not. Bare
     * domains are only caught on common endings, written lowercase, so
     * a name like ASP.NET is not taken for one.
     */
    private const PATTERNS = [
        self::EMAIL => '~[\w.+-]+@[\w-]+(?:\.[\w-]+)+~u',
        self::PHONE => '~(?:\+?\d[\s().-]*){9,}~',
    ];

    private const LINK_PATTERNS = [
        '~https?://|www\.~i',
        '~\b[a-z0-9-]+\.(?:com|net|org|io|co|me|info|biz|xyz|app|dev|ly|gg|link|site|online|shop|bd|in|uk)\b~',
    ];

    /**
     * @return array<int, string> the flags found, in a fixed order
     */
    public static function in(string ...$texts): array
    {
        $text = implode("\n", $texts);

        // An email address also contains a domain; reported once, as what
        // it is, rather than as an email and a link.
        $withoutEmails = preg_replace(self::PATTERNS[self::EMAIL], ' ', $text) ?? $text;

        return array_values(array_filter([
            preg_match(self::PATTERNS[self::EMAIL], $text) === 1 ? self::EMAIL : null,
            preg_match(self::PATTERNS[self::PHONE], $text) === 1 ? self::PHONE : null,
            collect(self::LINK_PATTERNS)->contains(fn ($pattern) => preg_match($pattern, $withoutEmails) === 1) ? self::LINK : null,
        ]));
    }

    public static function label(string $flag): string
    {
        return match ($flag) {
            self::EMAIL => __('an email address'),
            self::PHONE => __('a phone number'),
            self::LINK => __('a link'),
        };
    }
}
