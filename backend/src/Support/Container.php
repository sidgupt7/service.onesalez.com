<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class Container
{
    /** @var array<string, callable(self): object> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @param callable(self): object $factory */
    public function singleton(string $id, callable $factory): void
    {
        $this->bindings[$id] = $factory;
    }

    public function set(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            $instance = $this->instances[$id];
            if (!$instance instanceof $id) {
                throw new RuntimeException('Container type mismatch: ' . $id);
            }
            return $instance;
        }
        if (!isset($this->bindings[$id])) {
            throw new RuntimeException('Container binding not found: ' . $id);
        }
        $instance = ($this->bindings[$id])($this);
        if (!$instance instanceof $id) {
            throw new RuntimeException('Container factory type mismatch: ' . $id);
        }
        return $this->instances[$id] = $instance;
    }
}
