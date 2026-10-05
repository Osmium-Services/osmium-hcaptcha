<?php

declare(strict_types=1);

namespace Osmium\Services\Hcaptcha\Models;

/**
 * hCaptcha configuration + server-side token verification.
 *
 * File-based config (app/config/services/hcaptcha.json.php), matching the
 * Turnstile/reCAPTCHA convention. verify() is the callable a form handler
 * uses directly - this service has no admin-facing "protect this form"
 * toggle, a form either calls verify() or it doesn't. Same contract as
 * TurnstileConfig::verify() and RecaptchaConfig::verify().
 */
class HcaptchaConfig
{
    private const SITEVERIFY_URL = 'https://api.hcaptcha.com/siteverify';

    private static ?object $config = null;
    private static string $configPath = 'app/config/services/hcaptcha.json.php';

    public static function get(): object
    {
        $configLoaded = self::$config !== null;
        if ($configLoaded) return self::$config;

        $configExists = \file_exists(self::$configPath);
        if (!$configExists) {
            self::$config = self::defaults();
            return self::$config;
        }

        $content = \file_get_contents(self::$configPath);
        $jsonStart = \strpos(haystack: $content, needle: '{');

        $noJsonFound = $jsonStart === false;
        if ($noJsonFound) {
            self::$config = self::defaults();
            return self::$config;
        }

        $decoded = \json_decode(\substr(string: $content, offset: $jsonStart));

        self::$config = $decoded->hcaptcha ?? self::defaults();

        return self::$config;
    }

    public static function clearCache(): void
    {
        self::$config = null;
    }

    /**
     * Verify a submitted token (the "h-captcha-response" POST field) against
     * hCaptcha's siteverify endpoint. The site key is sent so a token issued
     * for another site key can't be redeemed here, and the visitor's IP when
     * there is one. Tokens are single-use.
     */
    public static function verify(string $token): bool
    {
        $config = self::get();

        $notConfigured = $token === '' || empty($config->secretKey ?? '');
        if ($notConfigured) return false;

        $fields = [
            'secret' => $config->secretKey,
            'response' => $token,
        ];

        $hasSiteKey = !empty($config->siteKey ?? '');
        if ($hasSiteKey) $fields['sitekey'] = $config->siteKey;

        $remoteIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $validIp = \filter_var($remoteIp, FILTER_VALIDATE_IP) !== false;
        if ($validIp) $fields['remoteip'] = $remoteIp;

        $ch = \curl_init(self::SITEVERIFY_URL);
        \curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => \http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = \curl_exec($ch);
        $curlError = \curl_error($ch);
        if ($curlError) return false;

        $decoded = \json_decode((string) $response, associative: true);

        return (bool) ($decoded['success'] ?? false);
    }

    private static function defaults(): object
    {
        return (object) [
            'enabled' => false,
            'siteKey' => '',
            'secretKey' => '',
        ];
    }
}
