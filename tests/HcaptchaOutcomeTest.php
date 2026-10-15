<?php

declare(strict_types=1);

namespace Tests\Services\Hcaptcha;

use Osmium\Services\Hcaptcha\Models\HcaptchaOutcome;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/HcaptchaOutcome.php';

/**
 * What an hCaptcha check came to, and what a form does about it.
 */
final class HcaptchaOutcomeTest extends TestCase
{
    public function testNoTokenIsMissing(): void
    {
        $this->assertSame(HcaptchaOutcome::MISSING, HcaptchaOutcome::classify(token: '', response: ['success' => true]));
    }

    public function testNoAnswerFromHcaptchaIsUnreachable(): void
    {
        $this->assertSame(HcaptchaOutcome::UNREACHABLE, HcaptchaOutcome::classify(token: 'abc', response: null));
    }

    public function testAnAnswerWithoutASuccessFieldIsUnreachable(): void
    {
        $this->assertSame(HcaptchaOutcome::UNREACHABLE, HcaptchaOutcome::classify(token: 'abc', response: ['oops' => 1]));
    }

    public function testSuccessIsPassed(): void
    {
        $this->assertSame(HcaptchaOutcome::PASSED, HcaptchaOutcome::classify(token: 'abc', response: ['success' => true]));
    }

    public function testRefusalIsFailed(): void
    {
        $this->assertSame(HcaptchaOutcome::FAILED, HcaptchaOutcome::classify(token: 'abc', response: ['success' => false, 'error-codes' => ['invalid-or-already-seen-response']]));
    }

    public function testFormsStopOnlyOnFailedOrMissing(): void
    {
        $this->assertTrue(HcaptchaOutcome::verdict(HcaptchaOutcome::PASSED));
        $this->assertFalse(HcaptchaOutcome::verdict(HcaptchaOutcome::FAILED));
        $this->assertFalse(HcaptchaOutcome::verdict(HcaptchaOutcome::MISSING));
    }

    public function testAnUnreachableHcaptchaFailsOpen(): void
    {
        $this->assertNull(HcaptchaOutcome::verdict(HcaptchaOutcome::UNREACHABLE));
    }
}
