<?php

declare(strict_types=1);

namespace Osmium\Services\Hcaptcha\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\Hcaptcha\Models\HcaptchaConfig;

/**
 * hCaptcha settings controller - full-page form POST/redirect,
 * same shape as AnalyticsController.
 *
 * Routes:
 *   - index() → /admin/settings/hcaptcha/  (GET shows the form, POST saves it)
 */
class HcaptchaController extends AdminController
{
    private const CONFIG_FILE_PATH = 'app/config/services/hcaptcha.json.php';
    private const DEFAULT_CONFIG = <<<'JSON'
        <?php exit(); ?>
        {
            "hcaptcha": {
                "enabled": false,
                "siteKey": "",
                "secretKey": ""
            }
        }
        JSON;

    public function index(): void
    {
        $isPost = $this->isPost();
        if ($isPost) $this->handleSubmit();

        $this->data['admin']['config']['hcaptcha'] = (array) HcaptchaConfig::get();
        $this->data['admin']['settingsSaved'] = $_SESSION['hcaptcha_settings_saved'] ?? false;
        $this->data['admin']['settingsError'] = $_SESSION['hcaptcha_settings_error'] ?? false;
        unset($_SESSION['hcaptcha_settings_saved'], $_SESSION['hcaptcha_settings_error']);

        $this->setView('hcaptcha/index.phtml');
    }

    private function handleSubmit(): void
    {
        $csrfValid = $this->admin->auth->validateCsrf();
        if (!$csrfValid) {
            $_SESSION['hcaptcha_settings_error'] = 'Invalid form submission. Please try again.';
            $this->redirect('settings/hcaptcha/');
        }

        $enabled = isset($_POST['enabled']);
        $siteKey = \trim($_POST['site_key'] ?? '');

        $postedSecretKey = \trim($_POST['secret_key'] ?? '');
        $secretKey = $postedSecretKey === '' ? (string) (HcaptchaConfig::get()->secretKey ?? '') : $postedSecretKey;

        $this->saveConfig($enabled, $siteKey, $secretKey);

        $this->admin->model->changelog->log(
            description: 'Updated hCaptcha settings',
            recordType: 'settings',
        );

        HcaptchaConfig::clearCache();

        $_SESSION['hcaptcha_settings_saved'] = true;
        $this->redirect('settings/hcaptcha/');
    }

    private function saveConfig(bool $enabled, string $siteKey, string $secretKey): void
    {
        $configExists = \file_exists(self::CONFIG_FILE_PATH);
        if (!$configExists) $this->ensureConfigDirectoryExists();

        $newJson = \json_encode(
            value: ['hcaptcha' => [
                'enabled' => $enabled,
                'siteKey' => $siteKey,
                'secretKey' => $secretKey,
            ]],
            flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        \file_put_contents(self::CONFIG_FILE_PATH, "<?php exit(); ?>\n" . $newJson . "\n");
    }

    private function ensureConfigDirectoryExists(): void
    {
        $dir = \dirname(self::CONFIG_FILE_PATH);
        $alreadyExists = \is_dir($dir);
        if (!$alreadyExists) \mkdir(directory: $dir, permissions: 0755, recursive: true);
    }

    private function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
}
