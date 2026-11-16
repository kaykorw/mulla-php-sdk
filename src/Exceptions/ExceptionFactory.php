<?php

declare(strict_types=1);

namespace Mulla\Exceptions;

use Mulla\Http\Response;

/**
 * Factory: turns an error HTTP response into the most specific exception class,
 * so callers can `catch (NotFoundException $e)` instead of inspecting status codes.
 */
final class ExceptionFactory
{
    /**
     * Extra guidance appended to the server message for common mistakes.
     *
     * @var array<string, string>
     */
    private const HINTS = [
        'API_KEY_INVALID' => 'Check that you are using a secret key (sk_...) that is active and not expired.',
        'API_KEY_ENVIRONMENT_MISMATCH' => 'Test keys (sk_test_) only work on the sandbox server and live keys (sk_live_) only on production. Check your key and baseUrl.',
        'API_KEY_SCOPE_DENIED' => 'This API key is missing the scope required by this endpoint. Add it in the Mulla dashboard.',
        'API_KEY_IP_DENIED' => 'This server\'s IP address is not in the key\'s allowed IP list.',
        'MERCHANT_PENDING_REVIEW' => 'Your merchant account is still under review by Mulla.',
        'IDEMPOTENCY_KEY_CONFLICT' => 'This idempotency key was already used. Fetch the existing resource instead of re-sending the request.',
        'VALIDATION_FAILED' => 'One or more parameters are invalid. Unknown fields are rejected too.',
    ];

    private function __construct() {}

    public static function fromResponse(Response $response): ApiException
    {
        $status = $response->statusCode;
        $body = $response->json();
        $errorCode = isset($body['errorCode']) && is_string($body['errorCode']) ? $body['errorCode'] : null;
        $message = self::buildMessage($status, $errorCode, $body);
        $args = [$message, $status, $errorCode, $body, $response->headers, $response->body];

        return match (true) {
            $status === 400 => new InvalidRequestException(...$args),
            $status === 401 => new AuthenticationException(...$args),
            $status === 403 => new PermissionException(...$args),
            $status === 404 => new NotFoundException(...$args),
            $status === 409 && $errorCode !== null && str_starts_with($errorCode, 'IDEMPOTENCY_KEY')
                => new IdempotencyException(...$args),
            $status === 409 => new ConflictException(...$args),
            $status === 429 => new RateLimitException(
                $message,
                $status,
                $errorCode,
                $body,
                $response->headers,
                $response->body,
                self::retryAfter($response, $body),
            ),
            $status >= 500 => new ApiServerException(...$args),
            default => new ApiException(...$args),
        };
    }

    /** @param array<string, mixed>|null $body */
    private static function buildMessage(int $status, ?string $errorCode, ?array $body): string
    {
        $serverMessage = $body['message'] ?? null;
        if (is_array($serverMessage)) {
            $serverMessage = implode('; ', array_map('strval', $serverMessage));
        }
        if (!is_string($serverMessage) || $serverMessage === '') {
            $serverMessage = "Mulla API request failed with HTTP {$status}";
        }

        $message = $errorCode !== null ? "[{$errorCode}] {$serverMessage}" : $serverMessage;
        if ($errorCode !== null && isset(self::HINTS[$errorCode])) {
            $message .= ' — ' . self::HINTS[$errorCode];
        }

        return $message;
    }

    /** @param array<string, mixed>|null $body */
    private static function retryAfter(Response $response, ?array $body): ?int
    {
        $header = $response->header('retry-after');
        if ($header !== null && is_numeric($header)) {
            return (int) $header;
        }
        if (isset($body['retryAfter']) && is_numeric($body['retryAfter'])) {
            return (int) $body['retryAfter'];
        }

        return null;
    }
}
