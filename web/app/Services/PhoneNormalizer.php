<?php

namespace App\Services;

class PhoneNormalizer
{
    /**
     * Turn whatever a customer typed at checkout into WhatsApp's digits-only
     * international format (no "+", no spaces). Returns null if it can't be a real number.
     */
    public static function normalize(?string $raw, string $defaultCountryCode): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $raw = trim($raw);
        $digits = preg_replace('/\D+/', '', $raw);
        $cc = preg_replace('/\D+/', '', $defaultCountryCode);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($raw, '+')) {
            // already international
        } elseif (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = $cc.substr($digits, 1);
        } elseif (strlen($digits) <= 10 && $cc !== '' && ! str_starts_with($digits, $cc)) {
            $digits = $cc.$digits;
        }

        // E.164 allows 8-15 digits in practice for mobile numbers.
        return (strlen($digits) >= 8 && strlen($digits) <= 15) ? $digits : null;
    }

    /** First usable phone found on an order payload. */
    public static function fromOrder(array $order, string $defaultCountryCode): ?string
    {
        $candidates = [
            $order['phone'] ?? null,
            $order['shipping_address']['phone'] ?? null,
            $order['billing_address']['phone'] ?? null,
            $order['customer']['phone'] ?? null,
            $order['customer']['default_address']['phone'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $normalized = self::normalize($candidate, $defaultCountryCode);
            if ($normalized) {
                return $normalized;
            }
        }

        return null;
    }
}
