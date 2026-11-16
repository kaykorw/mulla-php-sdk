<?php

declare(strict_types=1);

namespace Mulla\Http\Transport;

use Mulla\Exceptions\ConnectionException;
use Mulla\Exceptions\InvalidArgumentException;
use Mulla\Http\Request;
use Mulla\Http\Response;

/**
 * Adapter for any PSR-18 client (Guzzle 7, Symfony HttpClient, ...).
 *
 * Requires psr/http-client and a PSR-17 request + stream factory, e.g.:
 *
 *     $transport = new Psr18Transport(new \GuzzleHttp\Client(), new \GuzzleHttp\Psr7\HttpFactory());
 *
 * The PSR interfaces are duck-typed so psr/* stays an optional dependency.
 * Timeouts are controlled by the PSR-18 client's own configuration.
 */
final class Psr18Transport implements TransportInterface
{
    /**
     * @param object      $client         a Psr\Http\Client\ClientInterface
     * @param object      $requestFactory a Psr\Http\Message\RequestFactoryInterface
     * @param object|null $streamFactory  a Psr\Http\Message\StreamFactoryInterface; defaults to $requestFactory
     */
    public function __construct(
        private readonly object $client,
        private readonly object $requestFactory,
        private readonly ?object $streamFactory = null,
    ) {
        if (!interface_exists('Psr\Http\Client\ClientInterface')) {
            throw new InvalidArgumentException('Psr18Transport requires "psr/http-client". Run: composer require psr/http-client');
        }
        if (!is_a($client, 'Psr\Http\Client\ClientInterface')) {
            throw new InvalidArgumentException('Psr18Transport: $client must implement Psr\Http\Client\ClientInterface.');
        }
    }

    public function send(Request $request): Response
    {
        $streamFactory = $this->streamFactory ?? $this->requestFactory;

        $psrRequest = $this->requestFactory->createRequest($request->method, $request->url);
        foreach ($request->headers as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, $value);
        }
        if ($request->body !== null) {
            $psrRequest = $psrRequest->withBody($streamFactory->createStream($request->body));
        }

        try {
            $psrResponse = $this->client->sendRequest($psrRequest);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (is_a($e, 'Psr\Http\Client\ClientExceptionInterface')) {
                throw new ConnectionException('Could not reach Mulla: ' . $message, 0, $e);
            }

            throw $e;
        }

        $headers = [];
        foreach ($psrResponse->getHeaders() as $name => $values) {
            $headers[strtolower((string) $name)] = implode(', ', $values);
        }

        return new Response($psrResponse->getStatusCode(), $headers, (string) $psrResponse->getBody());
    }
}
