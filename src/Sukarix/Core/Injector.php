<?php

declare(strict_types=1);

namespace Sukarix\Core;

class Injector extends \Prefab
{
    private array $config;

    public function __construct()
    {
        $this->config = \Base::instance()->get('classes');
    }

    public function get($alias)
    {
        if (\Registry::exists($alias)) {
            return \Registry::get($alias);
        }

        if (!isset($this->config[$alias])) {
            throw new \Exception("Alias {$alias} is not defined in the configuration.");
        }

        $className = $this->config[$alias];

        if (!class_exists($className)) {
            throw new \Exception("Class {$className} does not exist.");
        }

        $reflector = new \ReflectionClass($className);

        if (!$reflector->isInstantiable()) {
            throw new \Exception("Class {$className} is not instantiable.");
        }

        // Check if the class extends \Prefab and call ::instance() if true
        if ($reflector->isSubclassOf(\Prefab::class)) {
            $instance = $className::instance();
        } else {
            $constructor = $reflector->getConstructor();

            if (null === $constructor) {
                $instance = new $className();
            } else {
                $parameters   = $constructor->getParameters();
                $dependencies = $this->resolveDependencies($parameters);
                $instance     = $reflector->newInstanceArgs($dependencies);
            }
        }

        \Registry::set($alias, $instance);

        return $instance;
    }

    protected function resolveDependencies($parameters)
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependency = $parameter->getClass();

            if (null === $dependency) {
                $dependencies[] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
            } else {
                $dependencies[] = $this->get($dependency->name);
            }
        }

        return $dependencies;
    }
}
