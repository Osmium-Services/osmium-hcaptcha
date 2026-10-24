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
     * Verify a submitted token (the "h-captcha-response" POST field) against hCaptcha's siteverify endpoint.
     */
    public static function verify(string $token): bool
    {
        return self::check($token) === HcaptchaOutcome::PASSED;
    }

    /**
     * Ask hCaptcha about a token and say what came of it (a HcaptchaOutcome constant). hCaptcha's own error codes (a
     * wrong secret key, a site key that does not match) go to the error log.
     */
    public static function check(string $token): string
    {
        $config = self::get();

        $noSecret = empty($config->secretKey ?? '');
        if ($noSecret) return HcaptchaOutcome::classify(token: $token, response: null);

        $noToken = $token === '';
        if ($noToken) return HcaptchaOutcome::MISSING;

        $response = self::askHcaptcha(config: $config, token: $token);

        $outcome = HcaptchaOutcome::classify(token: $token, response: $response);
        $bad = $outcome !== HcaptchaOutcome::PASSED;
        if ($bad) \error_log('hCaptcha check ' . $outcome . ': ' . \json_encode($response['error-codes'] ?? 'no answer from hCaptcha'));

        return $outcome;
    }

    /** @return ?array<string, mixed> hCaptcha's decoded answer, or null when it could not be asked */
    private static function askHcaptcha(object $config, string $token): ?array
    {
        $fields = [
            'secret' => $config->secretKey,
            'response' => $token,
        ];

        // The site key stops a token issued for another site key being redeemed here
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

        $body = \curl_exec($ch);
        $curlError = \curl_error($ch);
        if ($curlError || !\is_string($body)) return null;

        $decoded = \json_decode($body, associative: true);

        return \is_array($decoded) ? $decoded : null;
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
