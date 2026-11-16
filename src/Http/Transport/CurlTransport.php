<?php

declare(strict_types=1);

namespace Mulla\Http\Transport;

use Mulla\Exceptions\ConnectionException;
use Mulla\Http\Request;
use Mulla\Http\Response;
use Mulla\Support\Redactor;

/**
 * Default zero-dependency transport built on ext-curl.
 */
final class CurlTransport implements TransportInterface
{
    /** cURL errors that happen before any byte of the request is sent. */
    private const NOT_SENT_ERRORS = [
        5,  // CURLE_COULDNT_RESOLVE_PROXY
        6,  // CURLE_COULDNT_RESOLVE_HOST
        7,  // CURLE_COULDNT_CONNECT
        35, // CURLE_SSL_CONNECT_ERROR
        60, // CURLE_PEER_FAILED_VERIFICATION
    ];

    /**
     * @param array<int, mixed> $curlOptions Extra CURLOPT_* options, e.g. CURLOPT_PROXY or CURLOPT_CAINFO.
     *                                       They cannot override the URL, method, headers, body or TLS verification.
     */
    public function __construct(private readonly array $curlOptions = []) {}

    public function send(Request $request): Response
    {
        $handle = curl_init();
        if ($handle === false) {
            throw new ConnectionException('Could not initialise cURL.', 0, null, false);
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
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
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

        // SDK options win: extra options may add (proxy, CA bundle) but not weaken or redirect the request.
        curl_setopt_array($handle, $options + $this->curlOptions);

        $body = curl_exec($handle);
        if ($body === false) {
            $errno = curl_errno($handle);
            $mayHaveBeenSent = !in_array($errno, self::NOT_SENT_ERRORS, true)
                && (float) curl_getinfo($handle, CURLINFO_PRETRANSFER_TIME) > 0;

            $message = sprintf(
                'Could not reach Mulla at %s (cURL error %d: %s).',
                Redactor::url($request->url),
                $errno,
                curl_error($handle),
            );
            if ($mayHaveBeenSent && $request->method !== 'GET') {
                $message .= ' The request may have reached Mulla: check whether it was processed before sending it again.';
            }

            throw new ConnectionException($message, $errno, null, $mayHaveBeenSent);
        }

        return new Response((int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $responseHeaders, is_string($body) ? $body : '');
    }
}
