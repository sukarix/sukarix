<?php

declare(strict_types=1);

namespace Sukarix\Core;

class Processor extends \Prefab
{
    public function initialize($object)
    {
        foreach ($this->getAllTraits($object) as $trait) {
            $methodName = 'init' . (new \ReflectionClass($trait))->getShortName();
            if (method_exists($object, $methodName)) {
                $object->{$methodName}();
            }
        }
    }

    private function getAllTraits($object): array
    {
        do {
            $traits = array_merge($traits ?? [], class_uses($class ?? \get_class($object)));
        } while ($class = get_parent_class($class ?? $object));

        return array_unique(array_merge($traits, $this->getTraitsFromTraits($traits)));
    }

    private function getTraitsFromTraits(array $traits): array
    {
        return array_reduce($traits, function($carry, $trait) {
            return array_merge($carry, class_uses($trait), $this->getTraitsFromTraits(class_uses($trait)));
        }, []);
    }
}
