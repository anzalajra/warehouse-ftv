<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeysCommand extends Command
{
    protected $signature = 'push:generate-vapid {--show : Only display the keys without writing to .env}';

    protected $description = 'Generate VAPID keys for Web Push notifications and write them to .env';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $public = $keys['publicKey'];
        $private = $keys['privateKey'];

        $this->info('VAPID keys generated.');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY=' . $public);
        $this->line('VAPID_PRIVATE_KEY=' . $private);
        $this->line('VAPID_SUBJECT=mailto:admin@example.com');
        $this->newLine();

        if ($this->option('show')) {
            $this->warn('--show flag set, not writing to .env. Copy the values above manually.');
            return self::SUCCESS;
        }

        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            $this->error('.env file not found. Copy the values above manually.');
            return self::FAILURE;
        }

        $contents = file_get_contents($envPath);
        $contents = $this->setOrAppend($contents, 'VAPID_PUBLIC_KEY', $public);
        $contents = $this->setOrAppend($contents, 'VAPID_PRIVATE_KEY', $private);
        if (! preg_match('/^VAPID_SUBJECT=/m', $contents)) {
            $contents = $this->setOrAppend($contents, 'VAPID_SUBJECT', 'mailto:admin@example.com');
        }

        file_put_contents($envPath, $contents);

        $this->info('.env updated successfully.');
        $this->warn('Run: php artisan config:clear');
        $this->newLine();
        $this->line('For hosted environments (Dokploy/Docker/etc.) where .env writes are not persisted,');
        $this->line('add the three variables above to the environment configuration manually.');

        return self::SUCCESS;
    }

    protected function setOrAppend(string $contents, string $key, string $value): string
    {
        $line = $key . '=' . $value;
        if (preg_match('/^' . preg_quote($key, '/') . '=.*/m', $contents)) {
            return preg_replace('/^' . preg_quote($key, '/') . '=.*/m', $line, $contents);
        }
        return rtrim($contents, "\n") . "\n" . $line . "\n";
    }
}
