<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Closed-system code contract for unit / kit labels.
 *
 * Every printable QR & Barcode encodes the same string: "PREFIX:<serial>",
 * where PREFIX is the first 4 alphanumeric letters of the company name
 * (uppercased). The scanner strips the prefix before matching on serial, and
 * ignores anything that does not carry the prefix (foreign / public codes).
 */
class UnitCodeService
{
    /** First 4 alphanumeric chars of the company name, uppercased. */
    public function prefix(): string
    {
        $name = (string) Setting::get('site_name', config('app.name', 'Gearent'));
        $alnum = preg_replace('/[^A-Za-z0-9]/', '', $name);
        $prefix = strtoupper(substr((string) $alnum, 0, 4));

        // Guard against an empty/whitespace-only company name.
        return $prefix !== '' ? $prefix : 'GEAR';
    }

    /** Build the closed-system payload for a serial. */
    public function encode(string $serial): string
    {
        return $this->prefix().':'.$serial;
    }

    /**
     * Extract the serial from a scanned payload.
     * Returns null when the code is not a recognized system code.
     */
    public function decode(string $scanned): ?string
    {
        $scanned = trim($scanned);
        $needle = $this->prefix().':';

        if (strlen($scanned) <= strlen($needle)) {
            return null;
        }

        if (strncasecmp($scanned, $needle, strlen($needle)) !== 0) {
            return null;
        }

        $serial = substr($scanned, strlen($needle));

        return $serial !== '' ? $serial : null;
    }
}
