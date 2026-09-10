<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Schema;

class PlatformMailConfigurator
{
    public function apply(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        $settings = PlatformSetting::query()
            ->where('group', 'mail')
            ->pluck('value', 'key');

        if ($settings->isEmpty()) {
            return;
        }

        $mailer = $this->value($settings, 'mail_mailer');

        if ($mailer) {
            config(['mail.default' => $mailer]);
        }

        $mailScheme = $this->value($settings, 'mail_scheme');

        $smtpConfig = [
            'mail.mailers.smtp.host' => $this->value($settings, 'mail_host'),
            'mail.mailers.smtp.port' => $this->value($settings, 'mail_port'),
            'mail.mailers.smtp.username' => $this->value($settings, 'mail_username'),
            'mail.mailers.smtp.password' => $this->value($settings, 'mail_password'),
            'mail.mailers.smtp.scheme' => $mailScheme === 'none' ? null : $mailScheme,
            'mail.mailers.smtp.local_domain' => $this->value($settings, 'mail_ehlo_domain'),
        ];

        foreach ($smtpConfig as $key => $value) {
            if ($value !== null || $key === 'mail.mailers.smtp.scheme') {
                config([$key => $key === 'mail.mailers.smtp.port' ? (int) $value : $value]);
            }
        }

        $sendmailPath = $this->value($settings, 'mail_sendmail_path');

        if ($sendmailPath) {
            config(['mail.mailers.sendmail.path' => $sendmailPath]);
        }

        $fromAddress = $this->value($settings, 'mail_from_address');
        $fromName = $this->value($settings, 'mail_from_name');

        if ($fromAddress) {
            config(['mail.from.address' => $fromAddress]);
        }

        if ($fromName) {
            config(['mail.from.name' => $fromName]);
        }
    }

    protected function value($settings, string $key): ?string
    {
        $value = $settings[$key] ?? null;

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
