<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\DTO;

use Countable;
use IteratorAggregate;
use ArrayIterator;

final class TransportationSet implements IteratorAggregate, Countable
{
    /** @var array<string, TransportationRoute> */
    private array $routes = [];

    public function __construct(
        private bool $mandatory = false,
        private ?string $defaultRouteId = null
    ) {
    }

    public function isMandatory(): bool
    {
        return $this->mandatory;
    }

    public function setMandatory(bool $mandatory): void
    {
        $this->mandatory = $mandatory;
    }

    public function getDefaultRouteId(): ?string
    {
        return $this->defaultRouteId;
    }

    public function setDefaultRouteId(?string $defaultRouteId): void
    {
        $this->defaultRouteId = $defaultRouteId;
    }

    public function addRoute(TransportationRoute $route): void
    {
        $this->routes[$route->getId()] = $route;
    }

    public function getRoute(string $id): ?TransportationRoute
    {
        return $this->routes[$id] ?? null;
    }

    /**
     * @return TransportationRoute[]
     */
    public function all(): array
    {
        return array_values($this->routes);
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->all());
    }

    public function count(): int
    {
        return count($this->routes);
    }

    public function toArray(): array
    {
        return [
            'mandatory' => $this->mandatory,
            'defaultRouteId' => $this->defaultRouteId,
            'routes' => array_map(
                static fn (TransportationRoute $route): array => $route->toArray(),
                $this->all()
            ),
        ];
    }
}

