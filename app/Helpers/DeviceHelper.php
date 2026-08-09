<?php

namespace App\Helpers;

class DeviceHelper
{
    // ── Friendly "Browser on OS" Label From User-Agent ─────────────
    public static function label(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'Unknown device';
        }

        $browser = match (true) {
            str_contains($userAgent, 'Edg/')      => 'Edge',
            str_contains($userAgent, 'OPR/')      => 'Opera',
            str_contains($userAgent, 'Chrome/')   => 'Chrome',
            str_contains($userAgent, 'CriOS/')    => 'Chrome',
            str_contains($userAgent, 'Firefox/')  => 'Firefox',
            str_contains($userAgent, 'Safari/') && str_contains($userAgent, 'Version/') => 'Safari',
            default => 'Browser',
        };

        $os = match (true) {
            str_contains($userAgent, 'Windows')        => 'Windows',
            str_contains($userAgent, 'iPhone')          => 'iPhone',
            str_contains($userAgent, 'iPad')            => 'iPad',
            str_contains($userAgent, 'Mac OS X')        => 'Mac',
            str_contains($userAgent, 'Android')         => 'Android',
            str_contains($userAgent, 'Linux')           => 'Linux',
            default => null,
        };

        return $os ? "{$browser} on {$os}" : $browser;
    }
}
