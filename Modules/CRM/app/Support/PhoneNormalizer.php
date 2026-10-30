<?php

namespace Modules\CRM\Support;

/**
 * Brings the ways one phone number gets typed to a single form for the duplicate check:
 * digits only, with the country code and without the local leading 0.
 *
 *   "077 123-4567", "0771234567", "+94 77 123 4567", "0094771234567", "771234567"  →  "94771234567"
 *
 * Numbers typed with "+" or "00" keep their own country code. Without a country code the
 * local leading 0 is just dropped.
 */
final class PhoneNormalizer
{
    /** Fewer digits than this is not a phone number worth matching on. */
    public const MIN_DIGITS = 7;

    public static function normalize(?string $phone, ?string $countryCode = null): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '' || strlen($digits) < self::MIN_DIGITS) {
            return null;
        }

        $cc = preg_replace('/\D+/', '', (string) $countryCode);

        if (str_starts_with($phone, '+')) {
            $normal = $digits;
        } elseif (str_starts_with($digits, '00')) {
            $normal = substr($digits, 2);
        } elseif ($cc === '') {
            $normal = ltrim($digits, '0');
        } elseif (str_starts_with($digits, '0')) {
            $normal = $cc . ltrim($digits, '0');
        } elseif (str_starts_with($digits, $cc) && strlen($digits) > strlen($cc) + 8) {
            // Already carries the country code, typed without "+"
            $normal = $digits;
        } else {
            $normal = $cc . $digits;
        }

        return $normal === '' ? null : substr($normal, 0, 32);
    }

    /** The trailing digits used to narrow down candidates before comparing normalised numbers. */
    public static function tail(string $normalized, int $length = self::MIN_DIGITS): string
    {
        return substr($normalized, -$length);
    }
}
