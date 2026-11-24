---
title: Protect your forms with hCaptcha
tags: hcaptcha, captcha, spam, bots, forms, privacy, site key, secret key
page: admin/settings/hcaptcha
---

**hCaptcha** is a privacy-focused way to stop spam bots using your forms.

## Set it up

1. Create a site in your hCaptcha account and copy its **Site Key** and **Secret Key**.
2. Open **hCaptcha Settings**, enter both keys and tick **Enabled**.
3. Press **Save Settings**.

Ticking Enabled only loads the hCaptcha script. A form is protected only if your site's design adds the hCaptcha box to it and checks the result, so ask your developer to wire it into each form you want protected.

Cloudflare Turnstile is a similar alternative, and you only need one of them.
