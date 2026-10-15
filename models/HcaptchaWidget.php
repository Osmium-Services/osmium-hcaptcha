<?php

declare(strict_types=1);

namespace Osmium\Services\Hcaptcha\Models;

/**
 * The markup a form gets for its hCaptcha widget. Pure: no config, no request.
 *
 * The visitor has to tick the box before a token exists, and a form posted without one is refused, so the form's
 * submit buttons wait until the widget has been solved. Unlike an invisible widget there is no timed release: a visitor
 * who cannot solve it could not send the form anyway, and the box is on screen to explain why the button is waiting.
 */
class HcaptchaWidget
{
    /**
     * @param string $callbackId Letters and digits only, unique on the page (several forms may each have a widget)
     */
    public static function html(string $siteKey, string $callbackId, string $nonce): string
    {
        $key = \htmlspecialchars($siteKey, ENT_QUOTES);
        $id = \preg_replace('/[^A-Za-z0-9]/', '', $callbackId);
        $nonce = \htmlspecialchars($nonce, ENT_QUOTES);

        return <<<HTML
<div class="h-captcha" data-sitekey="{$key}" data-callback="hcaptchaDone{$id}" data-expired-callback="hcaptchaWait{$id}" data-error-callback="hcaptchaWait{$id}"></div>
<script nonce="{$nonce}">
(function () {
    var widget = document.currentScript.previousElementSibling;
    var form = widget ? widget.closest('form') : null;
    if (!form) return;
    var solved = false;
    function set(waiting) { // The buttons are looked up each time: this runs before the form's own buttons are parsed
        var buttons = form.querySelectorAll('[type="submit"]');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].disabled = waiting;
            buttons[i].style.opacity = waiting ? '0.6' : '';
            buttons[i].style.cursor = waiting ? 'not-allowed' : '';
        }
    }
    window.hcaptchaDone{$id} = function () { solved = true; set(false); };
    window.hcaptchaWait{$id} = function () { solved = false; set(true); }; // Expired or errored: solve it again
    function hold() { if (!solved) set(true); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', hold); else hold();
})();
</script>
HTML;
    }
}
