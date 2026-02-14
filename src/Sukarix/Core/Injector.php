<?php

declare(strict_types=1);

namespace Sukarix\Core;

/**
 * Simple Dependency Injector for Sukarix Framework
 * Following F3 philosophy - lightweight and practical
 */
class Injector extends \Prefab
{
    private array $config;
    private array $instances = [];

    public function __construct()
    {
        $this->config = \Base::instance()->get('classes') ?? [];
    }

    /**
     * Get an instance
     */
    public function get(string $alias)
    {
        // Check if instance already exists
        if (isset($this->instances[$alias])) {
            return $this->instances[$alias];
        }

        // Check Registry for backward compatibility
        if (\Registry::exists($alias)) {
            return \Registry::get($alias);
        }

        // Resolve class name
        $className = $this->resolveClassName($alias);

        if (!class_exists($className)) {
            throw new \Exception("Class {$className} does not exist.");
        }

        $reflector = new \ReflectionClass($className);

        if (!$reflector->isInstantiable()) {
            throw new \Exception("Class {$className} is not instantiable.");
        }

        // Create instance
        $instance = $this->createInstance($className, $reflector);

        // Cache instance
        $this->instances[$alias] = $instance;
        \Registry::set($alias, $instance);

        return $instance;
    }

    /**
     * Set a specific instance
     */
    public function set(string $alias, $instance): void
    {
        $this->instances[$alias] = $instance;
        \Registry::set($alias, $instance);
    }

    /**
     * Check if a service is registered
     */
    public function has(string $alias): bool
    {
        return isset($this->instances[$alias]) || 
               isset($this->config[$alias]) || 
               \Registry::exists($alias);
    }

    /**
     * Create instance with simple dependency injection
     */
    private function createInstance(string $className, \ReflectionClass $reflector)
    {
        // Check if the class extends \Prefab and call ::instance() if true
        if ($reflector->isSubclassOf(\Prefab::class)) {
            return $className::instance();
        }

        $constructor = $reflector->getConstructor();

        if (null === $constructor) {
            return new $className();
        }

        $parameters = $constructor->getParameters();
        $dependencies = $this->resolveDependencies($parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve class name from alias
     */
    private function resolveClassName(string $alias): string
    {
        // Check explicit configuration
        if (isset($this->config[$alias])) {
            return $this->config[$alias];
        }

        // Check if alias is a class name
        if (class_exists($alias)) {
            return $alias;
        }

        throw new \Exception("Alias {$alias} is not defined in the configuration.");
    }

    /**
     * Resolve dependencies with auto-wiring
     */
    private function resolveDependencies($parameters)
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type === null) {
                // No type hint, use default value or null
                $dependencies[] = $parameter->isDefaultValueAvailable() 
                    ? $parameter->getDefaultValue() 
                    : null;
            } else {
                $typeName = $type->getName();
                
                // Handle built-in types
                if ($parameter->getType()->isBuiltin()) {
                    $dependencies[] = $this->resolveBuiltInType($parameter, $typeName);
                } else {
                    // Try to resolve class dependency
                    try {
                        $dependencies[] = $this->get($typeName);
                    } catch (\Exception $e) {
                        // Use default value if available
                        $dependencies[] = $parameter->isDefaultValueAvailable() 
                            ? $parameter->getDefaultValue() 
                            : null;
                    }
                }
            }
        }

        return $dependencies;
    }

    /**
     * Resolve built-in type dependencies
     */
    private function resolveBuiltInType(\ReflectionParameter $parameter, string $typeName)
    {
        // Try to get from configuration
        $configKey = strtolower($typeName);
        if (\Base::instance()->exists($configKey)) {
            return \Base::instance()->get($configKey);
        }

        // Use default value if available
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        // Return default values for common types
        switch ($typeName) {
            case 'string':
                return '';
            case 'int':
            case 'integer':
                return 0;
            case 'float':
            case 'double':
                return 0.0;
            case 'bool':
            case 'boolean':
                return false;
            case 'array':
                return [];
            default:
                return null;
        }
    }

    /**
     * Clear all instances (useful for testing)
     */
    public function clear(): void
    {
        $this->instances = [];
        
        // Clear Registry too
        foreach ($this->instances as $alias => $instance) {
            \Registry::clear($alias);
        }
    }
}
