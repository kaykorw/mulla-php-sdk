<?php

declare(strict_types=1);

namespace Mulla\Models;

/**
 * A page of results from a list endpoint.
 *
 *  - foreach ($collection as $item)   iterates EVERY item across all pages (fetches lazily)
 *  - $collection->data                only the items of this page
 *  - $collection->nextPage()          the next page, or null
 *
 * Iterator pattern: pagination is hidden behind a plain foreach.
 *
 * @template T of AbstractModel
 * @implements \IteratorAggregate<int, T>
 */
final class Collection implements \IteratorAggregate, \Countable
{
    /** @var callable(int): Collection<T>|null */
    private $pageFetcher;

    /**
     * @param list<T> $data
     * @param (callable(int): Collection<T>)|null $pageFetcher loads a page by number
     */
    public function __construct(
        public readonly array $data,
        public readonly int $total,
        public readonly int $page,
        public readonly int $limit,
        public readonly int $totalPages,
        ?callable $pageFetcher = null,
    ) {
        $this->pageFetcher = $pageFetcher;
    }

    /**
     * Builds a collection from either the paginated shape
     * {data, total, page, limit, total_pages} or a plain JSON array.
     *
     * @template M of AbstractModel
     * @param class-string<M>                    $class
     * @param array<mixed>|null                  $response
     * @param (callable(int): Collection<M>)|null $pageFetcher
     * @return Collection<M>
     */
    public static function fromResponse(string $class, ?array $response, ?callable $pageFetcher = null): self
    {
        $response ??= [];
        if (array_is_list($response)) {
            $items = ModelFactory::many($class, $response);

            return new self($items, count($items), 1, max(1, count($items)), 1);
        }

        $items = ModelFactory::many($class, is_array($response['data'] ?? null) ? $response['data'] : []);
        $limit = (int) ($response['limit'] ?? count($items));
        $total = (int) ($response['total'] ?? count($items));

        return new self(
            data: $items,
            total: $total,
            page: (int) ($response['page'] ?? 1),
            limit: max(1, $limit),
            totalPages: (int) ($response['total_pages'] ?? ($limit > 0 ? (int) ceil($total / $limit) : 1)),
            pageFetcher: $pageFetcher,
        );
    }

    public function hasMore(): bool
    {
        return $this->page < $this->totalPages && $this->pageFetcher !== null;
    }

    /** @return Collection<T>|null */
    public function nextPage(): ?self
    {
        $fetch = $this->pageFetcher;
        if ($fetch === null || $this->page >= $this->totalPages) {
            return null;
        }

        return $fetch($this->page + 1);
    }

    /** Items on this page only. */
    public function count(): int
    {
        return count($this->data);
    }

    public function isEmpty(): bool
    {
        return $this->data === [];
    }

    /** @return T|null */
    public function first(): ?AbstractModel
    {
        return $this->data[0] ?? null;
    }

    /** @return \Generator<int, T> */
    public function getIterator(): \Generator
    {
        $page = $this;
        do {
            foreach ($page->data as $item) {
                yield $item;
            }
            $page = $page->nextPage();
        } while ($page !== null && !$page->isEmpty());
    }
}
