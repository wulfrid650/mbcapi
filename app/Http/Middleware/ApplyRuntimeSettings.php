<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;

class ApplyRuntimeSettings
{
    private const PLACEHOLDER_MAIL_HOSTS = [
        'smtp.example.com',
        'mail.example.com',
    ];

    public function handle(Request $request, Closure $next)
    {
        $this->applyMailSettings();

        return $next($request);
    }

    private function applyMailSettings(): void
    {
        try {
            $settings = SiteSetting::getGroup('email');
        } catch (\Throwable $e) {
            return;
        }

        if (empty($settings)) {
            return;
        }

        $config = [];

        $mailer = $settings['mail_mailer'] ?? null;
        if (!empty($mailer)) {
            $config['mail.default'] = $mailer;
        }

        $mailHost = $this->resolveMailHostSetting(
            $settings['mail_host'] ?? null,
            config('mail.mailers.smtp.host'),
        );
        if (!empty($mailHost)) {
            $config['mail.mailers.smtp.host'] = $mailHost;
        }
        if (!empty($settings['mail_port'])) {
            $config['mail.mailers.smtp.port'] = (int) $settings['mail_port'];
        }
        if (!empty($settings['mail_username'])) {
            $config['mail.mailers.smtp.username'] = $settings['mail_username'];
        }
        if (array_key_exists('mail_password', $settings) && $settings['mail_password'] !== '') {
            $config['mail.mailers.smtp.password'] = $settings['mail_password'];
        }
        if (array_key_exists('mail_encryption', $settings)) {
            $encryption = strtolower(trim((string) $settings['mail_encryption']));
            if ($encryption === 'ssl') {
                $config['mail.mailers.smtp.scheme'] = 'smtps';
            } elseif ($encryption === 'tls' || $encryption === 'starttls') {
                $config['mail.mailers.smtp.scheme'] = 'smtp';
            } else {
                $config['mail.mailers.smtp.scheme'] = null;
            }
            $config['mail.mailers.smtp.encryption'] = $encryption ?: null;
        }

        if (!empty($settings['mail_from_address'])) {
            $config['mail.from.address'] = $settings['mail_from_address'];
        }
        if (!empty($settings['mail_from_name'])) {
            $config['mail.from.name'] = $settings['mail_from_name'];
        }

        if (!empty($config)) {
            config($config);
        }
    }

    private function resolveMailHostSetting(mixed $storedHost, mixed $configuredHost): ?string
    {
        $storedHost = is_string($storedHost) ? trim($storedHost) : null;
        $configuredHost = is_string($configuredHost) ? trim($configuredHost) : null;

        if ($storedHost === null || $storedHost === '') {
            return $configuredHost;
        }

        if ($this->isPlaceholderMailHost($storedHost) && !$this->isPlaceholderMailHost($configuredHost)) {
            return $configuredHost;
        }

        return $storedHost;
    }

    private function isPlaceholderMailHost(?string $host): bool
    {
        if ($host === null || $host === '') {
            return false;
        }

        $host = strtolower(trim($host));

        return in_array($host, self::PLACEHOLDER_MAIL_HOSTS, true)
            || str_ends_with($host, '.example.com')
            || str_ends_with($host, '.example.org')
            || str_ends_with($host, '.example.net');
    }
}
