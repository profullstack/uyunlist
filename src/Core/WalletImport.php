<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Parse a pasted "wallet block" (e.g. exported from coinpay) into per-coin
 * receiving addresses. Deliberately lenient about layout — each line is scanned
 * for a coin label (code or name) followed by an address-looking token, so it
 * copes with formats like:
 *
 *   BTC: bc1q...              Bitcoin  bc1q...
 *   XMR = 4...                monero — 4...
 *   ETH 0x...                 Solana: <addr>
 *
 * Returns an array keyed by canonical code (BTC/XMR/ETH/SOL/DOGE); unknown or
 * unparseable lines are ignored.
 */
class WalletImport
{
    /** code => alias words (lowercase) recognised as a label for that coin. */
    private const ALIASES = [
        'BTC'  => ['btc', 'bitcoin', 'xbt'],
        'XMR'  => ['xmr', 'monero'],
        'ETH'  => ['eth', 'ethereum', 'ether'],
        'SOL'  => ['sol', 'solana'],
        'DOGE' => ['doge', 'dogecoin'],
    ];

    /** @return array<string,string> code => address */
    public static function parse(string $block): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $block) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            foreach (self::ALIASES as $code => $aliases) {
                if (isset($out[$code])) {
                    continue;
                }
                foreach ($aliases as $alias) {
                    // <alias> <optional label junk / separators> <address token>
                    $re = '/\b' . preg_quote($alias, '/') . '\b[^A-Za-z0-9]*([A-Za-z0-9]{12,120})\b/i';
                    if (preg_match($re, $line, $m)) {
                        $addr = $m[1];
                        if (self::looksLikeAddress($code, $addr)) {
                            $out[$code] = $addr;
                        }
                        break;
                    }
                }
            }
        }
        return $out;
    }

    /**
     * Cheap sanity check so a stray word isn't stored as an address. Not a full
     * validation — just filters obvious non-addresses.
     */
    public static function looksLikeAddress(string $code, string $addr): bool
    {
        $addr = trim($addr);
        switch (strtoupper($code)) {
            case 'ETH':
                return (bool)preg_match('/^0x[a-fA-F0-9]{40}$/', $addr);
            case 'BTC':
                return (bool)preg_match('/^(bc1[a-z0-9]{20,90}|[13][a-km-zA-HJ-NP-Z1-9]{25,39})$/', $addr);
            case 'XMR':
                return (bool)preg_match('/^[48][0-9A-Za-z]{94,105}$/', $addr);
            case 'SOL':
                return (bool)preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $addr);
            case 'DOGE':
                return (bool)preg_match('/^[DA9][1-9A-HJ-NP-Za-km-z]{25,40}$/', $addr);
            default:
                return strlen($addr) >= 12;
        }
    }

    /** The coin codes this app supports, in display order. */
    public static function codes(): array
    {
        return array_keys(self::ALIASES);
    }
}
