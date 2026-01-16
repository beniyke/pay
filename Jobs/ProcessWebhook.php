<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Process Webhook
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\Jobs;

use Pay\Services\WebhookService;
use Queue\BaseTask;
use Queue\Scheduler;

class ProcessWebhook extends BaseTask
{
    public function occurrence(): string
    {
        return self::once();
    }

    public function period(Scheduler $scheduler): Scheduler
    {
        return $scheduler;
    }

    protected function execute(): bool
    {
        // Resolve PayManager and create service instance manually or use container if bound properly.
        // Assuming WebhookService constructor updated to take PayManager.

        $service = resolve(WebhookService::class);
        $driver = $this->payload->get('driver');
        $rawPayload = $this->payload->get('payload'); // Raw string
        $signature = $this->payload->get('signature');

        $service->handle($driver, $rawPayload, $signature);

        return true;
    }

    protected function successMessage(): string
    {
        return "Webhook processed successfully for driver: {$this->payload->get('driver')}";
    }

    protected function failedMessage(): string
    {
        return "Failed to process webhook for driver: {$this->payload->get('driver')}";
    }
}
