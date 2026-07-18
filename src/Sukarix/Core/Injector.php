<?php

declare(strict_types=1);

namespace Sukarix\Core;

/**
 * Lightweight dependency injector backed by F3 configuration and Registry.
 */
class Injector extends \Prefab
{
    /** @var array<string, class-string> */
    private array $config;

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<class-string, string> */
    private array $resolving = [];

    /**
     * @param null|array<string, class-string> $config
     */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? \Base::instance()->get('classes') ?? [];
    }

    /**
     * Resolve a configured alias or an instantiable class name.
     *
     * @return mixed
     */
    public function get(string $alias)
    {
        if (\array_key_exists($alias, $this->instances)) {
            return $this->instances[$alias];
        }

        if (\Registry::exists($alias)) {
            return $this->instances[$alias] = \Registry::get($alias);
        }

        $className = $this->resolveClassName($alias);

        if (isset($this->resolving[$className])) {
            $chain   = array_values($this->resolving);
            $chain[] = $alias;

            throw new \LogicException('Circular dependency detected: ' . implode(' -> ', $chain));
        }

        $reflector = new \ReflectionClass($className);
        if (!$reflector->isInstantiable()) {
            throw new \LogicException("Class {$className} is not instantiable.");
        }

        $this->resolving[$className] = $alias;

        try {
            $instance = $this->createInstance($className, $reflector);
        } finally {
            unset($this->resolving[$className]);
        }

        $this->instances[$alias] = $instance;
        \Registry::set($alias, $instance);

        return $instance;
    }

    /**
     * Register a pre-built service instance or value.
     *
     * @param mixed $instance
     */
    public function set(string $alias, $instance): void
    {
        $this->instances[$alias] = $instance;
        \Registry::set($alias, $instance);
    }

    /**
     * Check whether an alias or class can be resolved.
     */
    public function has(string $alias): bool
    {
        if (\array_key_exists($alias, $this->instances)
            || \array_key_exists($alias, $this->config)
            || \Registry::exists($alias)
        ) {
            return true;
        }

        return class_exists($alias) && (new \ReflectionClass($alias))->isInstantiable();
    }

    /**
     * Clear one service or every service owned by this injector.
     */
    public function clear(?string $alias = null): void
    {
        $aliases = null === $alias ? array_keys($this->instances) : [$alias];

        foreach ($aliases as $serviceAlias) {
            $instance = $this->instances[$serviceAlias] ?? null;

            if (\Registry::exists($serviceAlias)) {
                $instance ??= \Registry::get($serviceAlias);
                \Registry::clear($serviceAlias);
            }

            unset($this->instances[$serviceAlias]);

            // Prefab stores its singleton under its class name as well as the
            // friendly service alias used by this injector.
            if (\is_object($instance) && $instance instanceof \Prefab) {
                $className = $instance::class;
                if (\Registry::exists($className) && \Registry::get($className) === $instance) {
                    \Registry::clear($className);
                }
            }
        }
    }

    /**
     * @param class-string             $className
     * @param \ReflectionClass<object> $reflector
     */
    private function createInstance(string $className, \ReflectionClass $reflector): object
    {
        if ($reflector->isSubclassOf(\Prefab::class)) {
            return $className::instance();
        }

        $constructor = $reflector->getConstructor();
        if (null === $constructor) {
            return $reflector->newInstance();
        }

        return $reflector->newInstanceArgs($this->resolveDependencies($constructor->getParameters()));
    }

    /**
     * @return class-string
     */
    private function resolveClassName(string $alias): string
    {
        if (\array_key_exists($alias, $this->config)) {
            $className = $this->config[$alias];
            if (!\is_string($className) || '' === $className) {
                throw new \UnexpectedValueException("Alias {$alias} must contain a class name.");
            }

            if (!class_exists($className)) {
                throw new \RuntimeException("Class {$className} configured for alias {$alias} does not exist.");
            }

            return $className;
        }

        if (class_exists($alias)) {
            return $alias;
        }

        throw new \InvalidArgumentException("Alias {$alias} is not defined in the configuration.");
    }

    /**
     * @param list<\ReflectionParameter> $parameters
     *
     * @return list<mixed>
     */
    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            if ($parameter->isVariadic()) {
                continue;
            }

            $dependencies[] = $this->resolveParameter($parameter);
        }

        return $dependencies;
    }

    /**
     * @return mixed
     */
    private function resolveParameter(\ReflectionParameter $parameter)
    {
        $f3 = \Base::instance();
        if ($f3->exists($parameter->getName())) {
            return $f3->get($parameter->getName());
        }

        $type = $parameter->getType();
        if (null === $type) {
            return $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
        }

        if ($type instanceof \ReflectionNamedType) {
            return $this->resolveNamedType($parameter, $type);
        }

        if ($type instanceof \ReflectionUnionType) {
            return $this->resolveUnionType($parameter, $type);
        }

        if ($type instanceof \ReflectionIntersectionType) {
            return $this->resolveIntersectionType($parameter, $type);
        }

        throw $this->unresolvedParameter($parameter);
    }

    /**
     * @return mixed
     */
    private function resolveNamedType(\ReflectionParameter $parameter, \ReflectionNamedType $type)
    {
        $typeName = $type->getName();

        if ($type->isBuiltin()) {
            // Keep compatibility with the original injector, which allowed a
            // scalar value to be configured under its type name.
            $f3 = \Base::instance();
            if ($f3->exists($typeName)) {
                return $f3->get($typeName);
            }

            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            if ($type->allowsNull()) {
                return null;
            }

            return $this->defaultBuiltinValue($typeName, $parameter);
        }

        $typeName     = $this->normalizeClassType($typeName, $parameter);
        $serviceAlias = $this->findServiceAliasForType($typeName);
        if (null !== $serviceAlias) {
            return $this->get($serviceAlias);
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($type->allowsNull()) {
            return null;
        }

        throw $this->unresolvedParameter($parameter);
    }

    /**
     * @return mixed
     */
    private function resolveUnionType(\ReflectionParameter $parameter, \ReflectionUnionType $type)
    {
        foreach ($type->getTypes() as $candidate) {
            if ($candidate->isBuiltin()) {
                continue;
            }

            $typeName     = $this->normalizeClassType($candidate->getName(), $parameter);
            $serviceAlias = $this->findServiceAliasForType($typeName);
            if (null !== $serviceAlias) {
                return $this->get($serviceAlias);
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($type->allowsNull()) {
            return null;
        }

        throw $this->unresolvedParameter($parameter);
    }

    /**
     * @return mixed
     */
    private function resolveIntersectionType(\ReflectionParameter $parameter, \ReflectionIntersectionType $type)
    {
        $typeNames = array_map(
            fn (\ReflectionNamedType $candidate): string => $this->normalizeClassType($candidate->getName(), $parameter),
            $type->getTypes()
        );

        foreach ($this->instances as $instance) {
            if (\is_object($instance) && $this->matchesEveryType($instance, $typeNames)) {
                return $instance;
            }
        }

        foreach ($this->config as $alias => $className) {
            if (\is_string($className) && $this->classMatchesEveryType($className, $typeNames)) {
                return $this->get($alias);
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw $this->unresolvedParameter($parameter);
    }

    /**
     * @param class-string $typeName
     */
    private function findServiceAliasForType(string $typeName): ?string
    {
        if (\array_key_exists($typeName, $this->instances)
            || \array_key_exists($typeName, $this->config)
            || \Registry::exists($typeName)
        ) {
            return $typeName;
        }

        foreach ($this->instances as $alias => $instance) {
            if (\is_object($instance) && is_a($instance, $typeName)) {
                return $alias;
            }
        }

        foreach ($this->config as $alias => $className) {
            if (\is_string($className) && is_a($className, $typeName, true)) {
                return $alias;
            }
        }

        if (class_exists($typeName) && (new \ReflectionClass($typeName))->isInstantiable()) {
            return $typeName;
        }

        return null;
    }

    /**
     * @param list<class-string> $typeNames
     */
    private function matchesEveryType(object $instance, array $typeNames): bool
    {
        foreach ($typeNames as $typeName) {
            if (!is_a($instance, $typeName)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param class-string       $className
     * @param list<class-string> $typeNames
     */
    private function classMatchesEveryType(string $className, array $typeNames): bool
    {
        foreach ($typeNames as $typeName) {
            if (!is_a($className, $typeName, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return class-string
     */
    private function normalizeClassType(string $typeName, \ReflectionParameter $parameter): string
    {
        $declaringClass = $parameter->getDeclaringClass();

        return match ($typeName) {
            'self', 'static' => $declaringClass?->getName() ?? $typeName,
            'parent' => $declaringClass?->getParentClass()?->getName() ?? $typeName,
            default  => $typeName,
        };
    }

    /**
     * @return array|bool|float|int|string
     */
    private function defaultBuiltinValue(string $typeName, \ReflectionParameter $parameter)
    {
        return match ($typeName) {
            'string' => '',
            'int'    => 0,
            'float'  => 0.0,
            'bool'   => false,
            'array',
            'iterable' => [],
            default    => throw $this->unresolvedParameter($parameter),
        };
    }

    private function unresolvedParameter(\ReflectionParameter $parameter): \RuntimeException
    {
        $className = $parameter->getDeclaringClass()?->getName() ?? 'callable';

        return new \RuntimeException(\sprintf(
            'Unable to resolve parameter $%s for %s::__construct().',
            $parameter->getName(),
            $className
        ));
    }
}
