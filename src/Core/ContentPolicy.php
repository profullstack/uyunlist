<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Content policy checks for user-submitted listings.
 *
 * The marketplace enforces a strict NO PORN / no sexually-explicit-content
 * rule. violatesNoPorn() runs a conservative keyword scan over the combined
 * listing text as defence-in-depth alongside human review. It is deliberately
 * simple and tuned to avoid false positives on legitimate listings:
 *
 *   - "word" terms match only as whole words on a normalized copy, so e.g.
 *     "anal" won't trip on "analysis" and "xxx" won't trip on size "XXXL".
 *   - "substring" terms are long and unambiguous enough that a bare substring
 *     match won't collide with everyday words.
 *
 * The lists live here so an operator can tune them in one place.
 */
final class ContentPolicy
{
    public const NO_PORN_MESSAGE =
        'This listing appears to contain pornographic or sexually explicit content, '
        . 'which is strictly prohibited on this site. Please remove it and try again.';

    /** Unambiguous terms blocked even as a substring (min ~6 chars, no collisions). */
    private const BLOCKED_SUBSTRINGS = [
        'porn', 'onlyfans', 'gangbang', 'cumshot', 'deepthroat', 'camgirl',
        'blowjob', 'handjob', 'creampie', 'bukkake', 'brothel', 'prostitut',
        'escort', 'fetish', 'hardcore', 'hentai', 'sexcam', 'sexchat',
    ];

    /** Shorter/ambiguous terms blocked only as standalone words. */
    private const BLOCKED_WORDS = [
        'xxx', 'nsfw', 'nude', 'nudes', 'nudity', 'anal', 'milf', 'bdsm',
        'gfe', 'incall', 'outcall', 'bbbj', 'nuru', 'hooker',
    ];

    /**
     * True when the combined text appears to advertise pornographic or
     * sexually-explicit content/services.
     */
    public static function violatesNoPorn(string ...$parts): bool
    {
        $text = strtolower(implode(' ', $parts));

        // Compact form (letters/digits only) catches spaced/punctuated evasion
        // for the unambiguous substring terms — "e s c o r t" -> "escort".
        $compact = preg_replace('/[^a-z0-9]+/', '', $text) ?? '';
        foreach (self::BLOCKED_SUBSTRINGS as $term) {
            if ($compact !== '' && str_contains($compact, $term)) {
                return true;
            }
        }

        // Whole-word match on a space-normalized copy for the ambiguous terms.
        $normalized = ' ' . trim(preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '') . ' ';
        foreach (self::BLOCKED_WORDS as $term) {
            if (str_contains($normalized, ' ' . $term . ' ')) {
                return true;
            }
        }

        return false;
    }
}
