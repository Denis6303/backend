<?php

namespace App\Console\Commands;

use App\Models\OrderIntent;
use Illuminate\Console\Command;

class ConfirmOrderIntentForTesting extends Command
{
    protected $signature = 'order-intent:confirm-for-testing
                            {key : UUID of the order intent}
                            {--secret= : Must match ORDER_INTENT_TEST_CONFIRM_SECRET in .env}';

    protected $description = 'Confirm a processing/confirming order intent without PSP verify (testing only).';

    public function handle(): int
    {
        $expected = (string) config('payments.test_confirm_secret', '');
        if ($expected === '') {
            $this->error('Set ORDER_INTENT_TEST_CONFIRM_SECRET in .env before using this command.');

            return self::FAILURE;
        }

        $secret = (string) $this->option('secret');
        if ($secret === '' || ! hash_equals($expected, $secret)) {
            $this->error('Invalid or missing --secret.');

            return self::FAILURE;
        }

        $key = (string) $this->argument('key');
        /** @var OrderIntent|null $intent */
        $intent = OrderIntent::query()->where('key', $key)->first();
        if (! $intent) {
            $this->error('Order intent not found for this key.');

            return self::FAILURE;
        }

        if ($intent->status === 'confirmed') {
            $this->info('Order intent is already confirmed.');

            return self::SUCCESS;
        }

        if (! in_array($intent->status, ['processing', 'confirming'], true)) {
            $this->error('Status must be processing or confirming (current: '.$intent->status.').');

            return self::FAILURE;
        }

        $intent->confirm();

        $this->info('Order intent confirmed. If QUEUE_CONNECTION=database, run the queue worker (or cron) so tickets are generated.');

        return self::SUCCESS;
    }
}
