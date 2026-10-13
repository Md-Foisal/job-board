<?php

namespace App\Support;

/**
 * Who the AI features send their material to, named the way the pages
 * tell people before they press an AI button, and what that company does
 * with it under the terms the product uses it on.
 *
 * The product runs on Anthropic's paid API. Groq and Gemini are here for
 * trying the features on a developer's machine with demo data, on their
 * free tiers, which never serve real users. Each says what it does with
 * what it is sent instead of repeating Anthropic's terms: Groq keeps
 * nothing by default; on Gemini's free tier what is sent may be used to
 * improve Google's products and read by people at Google.
 */
final class AiProvider
{
    public static function name(): string
    {
        return match (self::driver()) {
            'anthropic' => 'Anthropic',
            'gemini' => 'Google',
            'groq' => 'Groq',
            default => ucfirst(self::driver()),
        };
    }

    /**
     * One sentence on what the provider does with what is sent; $it is
     * the pronoun for it, "it" or "them".
     */
    public static function handling(string $it = 'it'): string
    {
        return match (self::driver()) {
            'anthropic' => __("Anthropic doesn't train on :it and, by default, deletes :it within 30 days.", ['it' => $it]),
            'gemini' => __("On Google's free tier, Google may use :it to improve its products, and people at Google may read :it.", ['it' => $it]),
            'groq' => __("By default, Groq doesn't keep :it once it has answered.", ['it' => $it]),
            default => __(':name handles :it under its own terms.', ['name' => self::name(), 'it' => $it]),
        };
    }

    private static function driver(): string
    {
        return (string) config('ai.providers.'.config('ai.default').'.driver', config('ai.default'));
    }
}
