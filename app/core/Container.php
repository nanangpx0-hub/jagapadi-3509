<?php

final class Container {
    private static ?self $instance = null;

    /** @var array<string, array{concrete:mixed, singleton:bool}> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function setInstance(?self $container): void {
        self::$instance = $container;
    }

    public function bind(string $abstract, callable|string|null $concrete = null, bool $singleton = false): void {
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'singleton' => $singleton,
        ];
    }

    public function singleton(string $abstract, callable|string|null $concrete = null): void {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, mixed $instance): void {
        $this->instances[$abstract] = $instance;
    }

    public function has(string $abstract): bool {
        return isset($this->bindings[$abstract])
            || isset($this->instances[$abstract])
            || class_exists($abstract);
    }

    public function make(string $abstract, array $parameters = []): mixed {
        if ($abstract === self::class) {
            return $this;
        }

        if (array_key_exists($abstract, $this->instances)) {
            return $this->instances[$abstract];
        }

        $binding = $this->bindings[$abstract] ?? null;
        $concrete = $binding['concrete'] ?? $abstract;
        $object = $this->build($concrete, $parameters);

        if (($binding['singleton'] ?? false) === true) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    private function build(callable|string $concrete, array $parameters = []): mixed {
        if (is_callable($concrete) && !is_string($concrete)) {
            return $concrete($this, $parameters);
        }

        if (!is_string($concrete) || !class_exists($concrete)) {
            throw new RuntimeException("Cannot resolve dependency: {$concrete}");
        }

        $reflection = new ReflectionClass($concrete);
        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Class {$concrete} is not instantiable");
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $dependencies = [];
        foreach ($constructor->getParameters() as $parameter) {
            $dependencies[] = $this->resolveParameter($parameter, $parameters);
        }

        return $reflection->newInstanceArgs($dependencies);
    }

    private function resolveParameter(ReflectionParameter $parameter, array $parameters): mixed {
        $name = $parameter->getName();
        if (array_key_exists($name, $parameters)) {
            return $parameters[$name];
        }

        $type = $parameter->getType();
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->make($type->getName());
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        throw new RuntimeException("Cannot resolve parameter \${$name}");
    }
}
