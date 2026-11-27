<?php

declare(strict_types=1);

namespace Mulla\Services;

use Mulla\Exceptions\InvalidArgumentException;
use Mulla\Http\RequestOptions;
use Mulla\Models\WebhookEndpoint;
use Mulla\Models\WebhookTestResult;

/**
 * Configure where Mulla sends webhooks (one endpoint per merchant).
 */
final class WebhookEndpointService extends AbstractService
{
    /**
     * Store the returned $endpoint->secret: it is shown only once.
     *
     * @param string|null                              $url     defaults to the client's "webhook_url" option
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function create(?string $url = null, bool $isActive = true, RequestOptions|array|null $options = null): WebhookEndpoint
    {
        $url ??= $this->config->webhookUrl;
        if ($url === null || trim($url) === '') {
            throw new InvalidArgumentException('Pass a webhook URL, or set the "webhook_url" option on the client.');
        }

        return $this->createModel(WebhookEndpoint::class, 'webhooks', ['url' => $url, 'is_active' => $isActive], $options);
    }

    /** @param RequestOptions|array<string, mixed>|null $options */
    public function retrieve(RequestOptions|array|null $options = null): WebhookEndpoint
    {
        return $this->retrieveModel(WebhookEndpoint::class, 'webhooks', [], $options);
    }

    /**
     * @param array{url?: string, is_active?: bool} $changes
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function update(array $changes, RequestOptions|array|null $options = null): WebhookEndpoint
    {
        return $this->updateModel(WebhookEndpoint::class, 'webhooks', $changes, $options);
    }

    /**
     * Issues a new signing secret. The old one stops working immediately.
     *
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function rotateSecret(RequestOptions|array|null $options = null): WebhookEndpoint
    {
        return $this->createModel(WebhookEndpoint::class, 'webhooks/rotate-secret', [], $options);
    }

    /**
     * Sends a signed "webhook.test" event to your endpoint and reports what it answered.
     *
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function test(RequestOptions|array|null $options = null): WebhookTestResult
    {
        return $this->createModel(WebhookTestResult::class, 'webhooks/test', [], $options);
    }
}
