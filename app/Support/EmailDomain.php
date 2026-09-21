<?php

namespace App\Support;

/**
 * The small amount of domain handling company verification needs: the
 * domain of an email address, the host of a website, whether one belongs
 * to the other, and whether a domain is a free mailbox anyone can sign up
 * for -- which says nothing about who someone works for.
 */
final class EmailDomain
{
    /**
     * Free mailbox providers. An address here proves nothing about an
     * employer, so it can never count as a match.
     */
    public const PERSONAL = [
        'gmail.com', 'googlemail.com', 'yahoo.com', 'ymail.com', 'outlook.com',
        'hotmail.com', 'live.com', 'msn.com', 'icloud.com', 'me.com', 'aol.com',
        'proton.me', 'protonmail.com', 'gmx.com', 'mail.com', 'yandex.com', 'zoho.com',
    ];

    public static function of(string $email): string
    {
        return strtolower((string) substr(strrchr($email, '@') ?: '', 1));
    }

    public static function host(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $host = parse_url(str_contains($url, '://') ? $url : "https://{$url}", PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return preg_replace('/^www\./', '', strtolower($host));
    }

    public static function isPersonal(string $domain): bool
    {
        return in_array($domain, self::PERSONAL, true);
    }

    /**
     * hr.acme.com belongs to acme.com; acme.com.evil.io does not.
     */
    public static function belongsTo(string $domain, string $host): bool
    {
        return $domain === $host || str_ends_with($domain, '.'.$host);
    }
}
