<?php

declare(strict_types=1);

namespace Mulla\Services;

use Mulla\Enums\ApiScope;
use Mulla\Exceptions\InvalidArgumentException;
use Mulla\Exceptions\PermissionException;
use Mulla\Http\ApiRequestor;
use Mulla\Http\RequestOptions;
use Mulla\Models\AbstractModel;
use Mulla\Models\Collection;
use Mulla\Models\ModelFactory;
use Mulla\MullaConfig;
use Mulla\Params\ParamsInterface;
use Mulla\Support\Normalizer;

/**
 * Template Method base for every resource service.
 * Each public operation is: build path -> send -> hydrate into a model.
 * Concrete services only declare paths and model classes.
 */
abstract class AbstractService
{
    protected const SCOPE = null;

    public function __construct(
        protected readonly ApiRequestor $requestor,
        protected readonly MullaConfig $config,
    ) {}

    /**
     * Sends a request and, when the key lacks a scope, says which one.
     *
     * @param array<string, mixed>      $query
     * @param array<string, mixed>|null $body
     * @return array<mixed>|null
     */
    protected function call(string $method, string $path, array $query, ?array $body, RequestOptions $options): ?array
    {
        try {
            return $this->requestor->request($method, $path, $query, $body, $options);
        } catch (PermissionException $e) {
            throw $this->explainScope($e);
        }
    }

    private function explainScope(PermissionException $e): PermissionException
    {
        if ($e->getErrorCode() !== 'API_KEY_SCOPE_DENIED') {
            return $e;
        }

        $body = $e->getBody() ?? [];
        $scope = $body['requiredScope'] ?? $body['required_scope'] ?? (is_array($body['missing'] ?? null) ? implode(', ', $body['missing']) : null);
        $scope = is_string($scope) && $scope !== '' ? $scope : (static::SCOPE instanceof ApiScope ? static::SCOPE->value : null);
        if ($scope === null) {
            return $e;
        }

        return new PermissionException(
            $e->getMessage() . " Required scope: {$scope}.",
            $e->getStatusCode(),
            $e->getErrorCode(),
            $e->getBody(),
            $e->getHeaders(),
            $e->getRawBody(),
            $e,
        );
    }

    /**
     * @template T of AbstractModel
     * @param class-string<T>                            $model
     * @param ParamsInterface|array<string, mixed>|null  $params
     * @param RequestOptions|array<string, mixed>|null   $options
     * @return T
     */
    protected function createModel(string $model, string $path, ParamsInterface|array|null $params, RequestOptions|array|null $options): AbstractModel
    {
        return $this->send($model, 'POST', $path, [], Normalizer::params($params), $options);
    }

    /**
     * @template T of AbstractModel
     * @param class-string<T>                          $model
     * @param array<string, mixed>                     $query
     * @param RequestOptions|array<string, mixed>|null $options
     * @return T
     */
    protected function retrieveModel(string $model, string $path, array $query = [], RequestOptions|array|null $options = null): AbstractModel
    {
        return $this->send($model, 'GET', $path, $query, null, $options);
    }

    /**
     * @template T of AbstractModel
     * @param class-string<T>                           $model
     * @param ParamsInterface|array<string, mixed>|null $params
     * @param RequestOptions|array<string, mixed>|null  $options
     * @return T
     */
    protected function updateModel(string $model, string $path, ParamsInterface|array|null $params, RequestOptions|array|null $options): AbstractModel
    {
        return $this->send($model, 'PATCH', $path, [], Normalizer::params($params), $options);
    }

    /**
     * @template T of AbstractModel
     * @param class-string<T>                          $model
     * @param array<string, mixed>                     $filters
     * @param RequestOptions|array<string, mixed>|null $options
     * @return Collection<T>
     */
    protected function listModels(string $model, string $path, array $filters, RequestOptions|array|null $options): Collection
    {
        $requestOptions = RequestOptions::from($options);
        $fetch = function (int $page) use ($model, $path, $filters, $requestOptions): Collection {
            $response = $this->call('GET', $path, ['page' => $page] + $filters, null, $requestOptions);

            return Collection::fromResponse($model, $response);
        };

        $response = $this->call('GET', $path, $filters, null, $requestOptions);

        return Collection::fromResponse($model, $response, $fetch);
    }

    /**
     * @template T of AbstractModel
     * @param class-string<T>                          $model
     * @param array<string, mixed>                     $query
     * @param array<string, mixed>|null                $body
     * @param RequestOptions|array<string, mixed>|null $options
     * @return T
     */
    protected function send(string $model, string $method, string $path, array $query, ?array $body, RequestOptions|array|null $options): AbstractModel
    {
        $response = $this->call($method, $path, $query, $body, RequestOptions::from($options));

        return ModelFactory::make($model, $response);
    }

    /**
     * Polls $fetch until the returned model reports isFinal() or the timeout passes.
     * Returns the last fetched model either way; check its status.
     *
     * @template T of AbstractModel
     * @param callable(): T $fetch
     * @return T
     */
    protected function pollUntilFinal(callable $fetch, int $timeoutSeconds, int $intervalSeconds): AbstractModel
    {
        if ($intervalSeconds < 1 || $timeoutSeconds < 0) {
            throw new InvalidArgumentException('Polling interval must be >= 1 second and timeout >= 0.');
        }

        $deadline = time() + $timeoutSeconds;
        while (true) {
            $model = $fetch();
            if ((method_exists($model, 'isFinal') && $model->isFinal()) || time() >= $deadline) {
                return $model;
            }
            ($this->config->sleeper)((float) min($intervalSeconds, max(1, $deadline - time())));
        }
    }

    /** URL-encodes an id after checking it is not empty. */
    protected static function id(string $id, string $label = 'id'): string
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException("The {$label} cannot be empty.");
        }
        if ($id === '.' || $id === '..') {
            throw new InvalidArgumentException("The {$label} \"{$id}\" is not valid.");
        }

        return rawurlencode($id);
    }
}
