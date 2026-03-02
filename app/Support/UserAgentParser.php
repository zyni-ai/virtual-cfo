<?php

namespace App\Support;

class UserAgentParser
{
    /**
     * @return array{device_type: string, browser: string, os: string}
     */
    public static function parse(string $userAgent): array
    {
        return [
            'device_type' => self::detectDeviceType($userAgent),
            'browser' => self::detectBrowser($userAgent),
            'os' => self::detectOs($userAgent),
        ];
    }

    private static function detectDeviceType(string $ua): string
    {
        if (preg_match('/Mobile|Android.*Mobile|iPhone|iPod/i', $ua)) {
            return 'Mobile';
        }

        if (preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $ua)) {
            return 'Tablet';
        }

        return 'Desktop';
    }

    private static function detectBrowser(string $ua): string
    {
        if (preg_match('/Edg\//i', $ua)) {
            return 'Edge';
        }

        if (preg_match('/Chrome\//i', $ua)) {
            return 'Chrome';
        }

        if (preg_match('/Firefox\//i', $ua)) {
            return 'Firefox';
        }

        if (preg_match('/Safari\//i', $ua)) {
            return 'Safari';
        }

        return 'Other';
    }

    private static function detectOs(string $ua): string
    {
        if (preg_match('/Windows/i', $ua)) {
            return 'Windows';
        }

        if (preg_match('/iPhone|iPad|iPod/i', $ua)) {
            return 'iOS';
        }

        if (preg_match('/Macintosh|Mac OS/i', $ua)) {
            return 'macOS';
        }

        if (preg_match('/Android/i', $ua)) {
            return 'Android';
        }

        if (preg_match('/Linux/i', $ua)) {
            return 'Linux';
        }

        return 'Other';
    }
}
