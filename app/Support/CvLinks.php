<?php

namespace App\Support;

/**
 * The profile links a CV's text suggests: a LinkedIn profile, a GitHub
 * profile, and one other website as the portfolio. They are suggestions
 * for the candidate to confirm, never saved on their own.
 *
 * CVs often write these without a scheme ("linkedin.com/in/karim"), so
 * LinkedIn and GitHub addresses are recognised bare. Any other site needs
 * http(s):// or www. in front: without that, "Node.js" or "e.g." would
 * read as web addresses.
 *
 * Only profile addresses count. A LinkedIn company page is not the
 * candidate's profile, and a GitHub repository may belong to someone else
 * (a CV listing contributions to laravel/framework is not the candidate's
 * account), so only a single-name GitHub path is taken.
 */
final class CvLinks
{
    /**
     * GitHub's own pages that look like a user name in the first path
     * segment but are not one.
     */
    private const GITHUB_NON_USERS = [
        'about', 'apps', 'collections', 'contact', 'customer-stories', 'enterprise', 'events',
        'explore', 'features', 'topics', 'trending', 'issues', 'join', 'login', 'marketplace',
        'new', 'notifications', 'orgs', 'organizations', 'pricing', 'pulls', 'search',
        'security', 'settings', 'site', 'sponsors', 'team',
    ];

    private const MAX_LENGTH = 255;

    /**
     * @return array{linkedin_url: ?string, github_url: ?string, portfolio_url: ?string}
     */
    public static function from(string $text): array
    {
        $links = ['linkedin_url' => null, 'github_url' => null, 'portfolio_url' => null];

        foreach (self::addresses($text) as $address) {
            $url = str_contains($address, '://') ? $address : 'https://'.$address;
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

            if ($host === '' || strlen($url) > self::MAX_LENGTH || filter_var($url, FILTER_VALIDATE_URL) === false) {
                continue;
            }

            if ($host === 'linkedin.com' || str_ends_with($host, '.linkedin.com')) {
                if (preg_match('#^in/([^/?\#]+)$#i', $path, $match)) {
                    $links['linkedin_url'] ??= 'https://www.linkedin.com/in/'.$match[1];
                }

                continue;
            }

            if ($host === 'github.com' || $host === 'www.github.com') {
                if (preg_match('#^[a-z\d](?:[a-z\d-]{0,38})$#i', $path)
                    && ! in_array(strtolower($path), self::GITHUB_NON_USERS, true)) {
                    $links['github_url'] ??= 'https://github.com/'.$path;
                }

                continue;
            }

            if (preg_match('#^(https?://|www\.)#i', $address)) {
                $links['portfolio_url'] ??= $url;
            }
        }

        return $links;
    }

    /**
     * Everything in the text that looks like a web address, in the order
     * it appears, with trailing punctuation from the sentence removed.
     *
     * @return array<int, string>
     */
    private static function addresses(string $text): array
    {
        preg_match_all(
            '#(?<![@\w.])(?:https?://[^\s<>"\']+|www\.[^\s<>"\']+|(?:[a-z]{2,3}\.)?(?:linkedin|github)\.com/[^\s<>"\']+)#iu',
            $text,
            $matches,
        );

        return array_map(fn (string $address) => rtrim($address, '.,;:!?)]}\'"'), $matches[0]);
    }
}
