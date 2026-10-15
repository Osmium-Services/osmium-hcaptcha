<?php

declare(strict_types=1);

namespace Tests\Services\Hcaptcha;

use Osmium\Services\Hcaptcha\Models\HcaptchaWidget;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/HcaptchaWidget.php';

/**
 * The widget markup: the hCaptcha div plus the script that holds the form's submit buttons until it is solved.
 */
final class HcaptchaWidgetTest extends TestCase
{
    private function html(array $changes = []): string
    {
        $args = \array_merge(['siteKey' => 'KEY-1', 'callbackId' => 'reviews1', 'nonce' => 'abc123'], $changes);

        return HcaptchaWidget::html(siteKey: $args['siteKey'], callbackId: $args['callbackId'], nonce: $args['nonce']);
    }

    public function test_the_div_carries_the_site_key_and_its_callbacks(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('class="h-captcha" data-sitekey="KEY-1"', $html);
        $this->assertStringContainsString('data-callback="hcaptchaDonereviews1"', $html);
        $this->assertStringContainsString('data-expired-callback="hcaptchaWaitreviews1"', $html);
        $this->assertStringContainsString('data-error-callback="hcaptchaWaitreviews1"', $html);
    }

    public function test_the_script_carries_the_csp_nonce(): void
    {
        $this->assertStringContainsString('<script nonce="abc123">', $this->html());
    }

    public function test_the_site_key_is_escaped(): void
    {
        $html = $this->html(['siteKey' => '"><script>x</script>']);

        $this->assertStringNotContainsString('<script>x', $html);
    }

    public function test_the_callback_id_cannot_break_out_of_its_name(): void
    {
        $html = $this->html(['callbackId' => 'a-b"c d']);

        $this->assertStringContainsString('hcaptchaDoneabcd', $html);
        $this->assertStringNotContainsString('a-b"c', $html);
    }

    public function test_the_buttons_are_not_released_on_a_timer(): void
    {
        $this->assertStringNotContainsString('setTimeout', $this->html());
    }

    public function test_two_forms_get_their_own_callbacks(): void
    {
        $this->assertStringContainsString('hcaptchaDoneother2', $this->html(['callbackId' => 'other2']));
        $this->assertStringNotContainsString('hcaptchaDonereviews1', $this->html(['callbackId' => 'other2']));
    }
}
