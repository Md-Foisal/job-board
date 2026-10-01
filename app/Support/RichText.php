<?php

namespace App\Support;

/**
 * Stored rich text (already cleaned by SanitizedHtml) turned into plain
 * text for places that need words, not markup -- such as what is sent to
 * an AI model.
 */
final class RichText
{
    /**
     * Block ends become line breaks, tags and entities go, and runs of
     * blank space shrink. Null when nothing is left; cut to $max.
     */
    public static function plain(?string $html, int $max): ?string
    {
        if (blank($html)) {
            return null;
        }

        $text = preg_replace('~<\s*(?:br\s*/?|/p|/li|/h[1-6]|/div|/blockquote)\s*>~i', "\n", $html) ?? '';
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace(['/[ \t]+/u', '/\n\s*\n\s*/u'], [' ', "\n\n"], $text) ?? '';
        $text = trim($text);

        return $text === '' ? null : mb_substr($text, 0, $max);
    }
}
