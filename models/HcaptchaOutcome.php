<?php

declare(strict_types=1);

namespace Osmium\Services\Hcaptcha\Models;

/**
 * What an hCaptcha check came to, and what a form should do about it. Pure: no network, no database.
 */
class HcaptchaOutcome
{
    public const PASSED = 'passed';
    public const FAILED = 'failed';
    public const MISSING = 'missing'; // The visitor sent no token: the box was not ticked or the widget did not load
    public const UNREACHABLE = 'unreachable'; // hCaptcha could not be asked, or its answer made no sense

    /**
     * @param ?array<string, mixed> $response hCaptcha's decoded answer, null when there was none
     */
    public static function classify(string $token, ?array $response): string
    {
        $noToken = $token === '';
        if ($noToken) return self::MISSING;

        $noAnswer = $response === null || !\array_key_exists('success', $response);
        if ($noAnswer) return self::UNREACHABLE;

        return $response['success'] === true ? self::PASSED : self::FAILED;
    }

    /**
     * The answer to the form.verify hook: false stops the form. An unreachable hCaptcha fails open (null), so an
     * outage cannot lose a customer's submission; the form's own checks still apply.
     */
    public static function verdict(string $outcome): ?bool
    {
        return match ($outcome) {
            self::PASSED => true,
            self::FAILED, self::MISSING => false,
            default => null,
        };
    }
}
