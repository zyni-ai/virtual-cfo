<?php

namespace App\Support;

class GstinValidator
{
    /**
     * Validate a GSTIN (Goods and Services Tax Identification Number).
     *
     * Format: 2-digit state code + 10-char PAN + 1 entity code + 1 Z + 1 checksum
     * Total: 15 alphanumeric characters.
     */
    public static function isValid(?string $gstin): bool
    {
        if ($gstin === null || $gstin === '') {
            return false;
        }

        // Must be exactly 15 alphanumeric characters
        if (! preg_match('/^[0-9A-Z]{15}$/', $gstin)) {
            return false;
        }

        // State code (first 2 digits) must be between 01 and 38
        $stateCode = (int) substr($gstin, 0, 2);

        if ($stateCode < 1 || $stateCode > 38) {
            return false;
        }

        return true;
    }
}
