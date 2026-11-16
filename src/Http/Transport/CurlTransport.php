<?php

declare(strict_types=1);

namespace Mulla\Http\Transport;

use Mulla\Exceptions\ConnectionException;
use Mulla\Http\Request;
use Mulla\Http\Response;

/**
 * Default zero-dependency transport built on ext-curl.
 */
final class CurlTransport implements TransportInterface
{
    /**
     * @param array<int, mixed> $curlOptions Extra CURLOPT_* options (e.g. proxy settings)
     */
    public function __construct(private readonly array $curlOptions = [])
    {
    }

    public function send(Request $request): Response
    {
        $handle = curl_init();
        if ($handle === false) {
            throw new ConnectionException('Could not initialise cURL.');
        }

        $responseHeaders = [];
        $headerLines = [];
        foreach ($request->headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $options = [
            CURLOPT_URL => $request->url,
            CURLOPT_CUSTOMREQUEST => $request->method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT_MS => (int) ($request->timeout * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int) ($request->connectTimeout * 1000),
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ];
        if ($request->body !== null) {
            $options[CURLOPT_POSTFIELDS] = $request->body;
        }

        curl_setopt_array($handle, $this->curlOptions + $options);

        $body = curl_exec($handle);
        if ($body === false) {
            $error = curl_error($handle);
            $errno = curl_errno($handle);
            curl_close($handle);

            throw new ConnectionException(
                sprintf('Could not reach Mulla at %s (cURL error %d: %s).', $request->url, $errno, $error),
                $errno,
            );
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return new Response($status, $responseHeaders, is_string($body) ? $body : '');
    }
}
