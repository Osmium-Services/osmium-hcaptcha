<?php

declare(strict_types=1);

namespace Osmium\Services\Hcaptcha\Models;

/**
 * The handlers for the core form hooks (form.verify, form.widget). A form that fires those hooks is protected while
 * hCaptcha is enabled, and never has to name it.
 */
class HcaptchaHooks
{
    /**
     * form.verify: true when hCaptcha passed the visitor, false when it did not or sent no token, null when this
     * service is off or hCaptcha could not be asked (the form then relies on its own checks).
     *
     * @param array{form: string, post: array, dataSource: object} $payload
     */
    public static function verify(array $payload): ?bool
    {
        $off = !(HcaptchaConfig::get()->enabled ?? false);
        if ($off) return null;

        return HcaptchaOutcome::verdict(HcaptchaConfig::check((string) ($payload['post']['h-captcha-response'] ?? '')));
    }

    /**
     * form.widget: the hCaptcha box for the form (it holds the submit buttons until it is solved), or null when this
     * service is off.
     *
     * @param array{form: string} $payload
     */
    public static function widget(array $payload): ?string
    {
        $config = HcaptchaConfig::get();
        $notUsable = !($config->enabled ?? false) || empty($config->siteKey ?? '');
        if ($notUsable) return null;

        static $count = 0;
        $count++;

        return HcaptchaWidget::html(siteKey: (string) $config->siteKey, callbackId: \preg_replace('/[^A-Za-z0-9]/', '', (string) ($payload['form'] ?? '')) . $count, nonce: \Osmium\Core\OsmiumSecurity::requestNonce());
    }
}
