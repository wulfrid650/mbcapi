<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class SyncMailSettingsFromEnv extends Command
{
    protected $signature = 'settings:sync-mail-from-env
        {--test : Send a runtime SMTP test email after sync}
        {--to= : Recipient address for the test email}';

    protected $description = 'Sync SMTP settings from environment variables into SiteSettings.';

    private const SETTING_TO_ENV_MAP = [
        'mail_mailer' => 'MAIL_MAILER',
        'mail_host' => 'MAIL_HOST',
        'mail_port' => 'MAIL_PORT',
        'mail_username' => 'MAIL_USERNAME',
        'mail_password' => 'MAIL_PASSWORD',
        'mail_encryption' => 'MAIL_ENCRYPTION',
        'mail_from_address' => 'MAIL_FROM_ADDRESS',
        'mail_from_name' => 'MAIL_FROM_NAME',
    ];

    public function handle(): int
    {
        $updated = 0;

        foreach (self::SETTING_TO_ENV_MAP as $settingKey => $envKey) {
            $value = env($envKey);

            if ($value === null || $value === '') {
                $this->warn("Skipped {$settingKey}: env {$envKey} is empty.");
                continue;
            }

            $setting = SiteSetting::firstOrNew(['key' => $settingKey]);

            if (!$setting->exists) {
                $setting->group = 'email';
                $setting->type = $settingKey === 'mail_port' ? 'number' : 'text';
                $setting->label = strtoupper($settingKey);
                $setting->description = 'Created by settings:sync-mail-from-env command';
                $setting->is_public = false;
            }

            $setting->value = (string) $value;
            $setting->save();
            $updated++;

            if ($settingKey === 'mail_password') {
                $this->line("Updated {$settingKey}: [REDACTED]");
            } else {
                $this->line("Updated {$settingKey}: {$value}");
            }
        }

        $this->info("SMTP sync complete. {$updated} setting(s) updated.");

        if ($this->option('test')) {
            return $this->sendTestEmail();
        }

        return self::SUCCESS;
    }

    private function sendTestEmail(): int
    {
        $settings = SiteSetting::getGroup('email');

        $mailer = $settings['mail_mailer'] ?? null;
        if ($mailer) {
            Config::set('mail.default', $mailer);
        }

        if (!empty($settings['mail_host'])) {
            Config::set('mail.mailers.smtp.host', $settings['mail_host']);
        }
        if (!empty($settings['mail_port'])) {
            Config::set('mail.mailers.smtp.port', (int) $settings['mail_port']);
        }
        if (!empty($settings['mail_username'])) {
            Config::set('mail.mailers.smtp.username', $settings['mail_username']);
        }
        if (array_key_exists('mail_password', $settings) && $settings['mail_password'] !== '') {
            Config::set('mail.mailers.smtp.password', $settings['mail_password']);
        }
        if (array_key_exists('mail_encryption', $settings)) {
            $encryption = strtolower(trim((string) $settings['mail_encryption']));
            Config::set(
                'mail.mailers.smtp.encryption',
                in_array($encryption, ['', 'null', 'none'], true) ? null : $encryption
            );
        }
        if (!empty($settings['mail_from_address'])) {
            Config::set('mail.from.address', $settings['mail_from_address']);
        }
        if (!empty($settings['mail_from_name'])) {
            Config::set('mail.from.name', $settings['mail_from_name']);
        }

        $recipient = $this->option('to') ?: ($settings['mail_from_address'] ?? env('MAIL_FROM_ADDRESS'));

        if (!$recipient) {
            $this->error('No recipient available for SMTP test. Use --to=you@example.com');
            return self::FAILURE;
        }

        try {
            Mail::raw(
                'SMTP test from settings:sync-mail-from-env at ' . now()->toDateTimeString(),
                function ($message) use ($recipient): void {
                    $message->to($recipient)->subject('SMTP test - MBC');
                }
            );

            $this->info("SMTP test email sent successfully to {$recipient}.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('SMTP test failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
