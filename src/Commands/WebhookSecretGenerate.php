<?php

namespace Wonderfulso\WonderAb\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class WebhookSecretGenerate extends Command
{
    protected $signature = 'ab:webhook-secret';

    protected $description = 'Generate a secure webhook secret for goal registration';

    public function handle(): int
    {
        $secret = Str::random(64);

        $this->info('Webhook secret generated successfully!');
        $this->newLine();

        $this->line('Add this to your .env file:');
        $this->newLine();

        $this->info("WONDER_AB_WEBHOOK_SECRET={$secret}");
        $this->newLine();

        $this->line('Also set these optional configuration values:');
        $this->line('WONDER_AB_WEBHOOK_ENABLED=true');
        $this->line('WONDER_AB_WEBHOOK_RATE_LIMIT=60');
        $this->line('WONDER_AB_WEBHOOK_PATH=/ab/webhook/goal');
        $this->newLine();

        $this->comment('Webhook endpoint will be available at:');
        $this->comment(config('app.url') . '/api/ab/webhook/goal');
        $this->newLine();

        $this->warn('Keep this secret safe! Anyone with this secret can register goals for your A/B tests.');

        return self::SUCCESS;
    }
}
