<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Formats a listing's USD-based price (with the cached crypto conversion) for
 * display. The crypto amount is precomputed/refreshed server-side, so this does
 * no rate lookups.
 */
class Price
{
    public static function label(array $listing): string
    {
        $cents = (int)($listing['price_usd_cents'] ?? 0);
        if ($cents <= 0) {
            return 'Free / contact for price';
        }
        $out = '$' . number_format($cents / 100, 2) . ' USD';

        $crypto = (float)($listing['price_crypto'] ?? 0);
        $coin   = strtoupper((string)($listing['price_currency'] ?? ''));
        if ($crypto > 0 && $coin !== '') {
            $out .= ' (≈ ' . self::crypto($crypto) . ' ' . $coin . ')';
        }
        return $out;
    }

    public static function crypto(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 8, '.', ''), '0'), '.');
    }

    public static function usd(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }
}
