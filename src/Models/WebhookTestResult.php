<?php

declare(strict_types=1);

namespace Mulla\Models;

/**
 * Outcome of sending a test webhook to your endpoint.
 */
final class WebhookTestResult extends AbstractModel
{
    /** True when your endpoint answered 2xx. */
    public readonly bool $ok;
    public readonly ?int $statusCode;
    public readonly ?string $responseBody;
    public readonly ?string $error;

    protected function hydrate(array $data): void
    {
        $this->ok = self::bool($data, 'ok') ?? false;
        $this->statusCode = isset($data['status_code']) && is_numeric($data['status_code']) ? (int) $data['status_code'] : null;
        $this->responseBody = self::str($data, 'response_body');
        $this->error = self::str($data, 'error');
    }
}
