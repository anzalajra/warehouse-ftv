<?php

namespace App\Services\Mufise;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class MufiseSettings
{
    public static function enabled(): bool
    {
        return filter_var(Setting::get('mufise_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function url(): string
    {
        return rtrim((string) Setting::get('mufise_url', config('services.mufise.url')), '/');
    }

    public static function connectionId(): string
    {
        return (string) Setting::get('mufise_connection_id', config('services.mufise.connection_id'));
    }

    public static function key(): string
    {
        $cipher = Setting::get('mufise_api_key_encrypted');

        return $cipher ? Crypt::decryptString($cipher) : (string) config('services.mufise.api_key');
    }

    public static function validateUrl(string $url): void
    {
        $parts = parse_url($url);
        $local = in_array($parts['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true);
        if (! filter_var($url, FILTER_VALIDATE_URL) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || ! in_array($parts['path'] ?? '', ['', '/'], true) || ! in_array($parts['scheme'] ?? '', $local ? ['http', 'https'] : ['https'], true)) {
            throw ValidationException::withMessages(['data.url' => 'Gunakan URL HTTPS Mufise, atau HTTP localhost, tanpa path maupun kredensial.']);
        }
    }
}
