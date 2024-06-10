<?php

declare(strict_types=1);

namespace Sukarix\Core;

class Processor extends \Prefab
{
    public function initialize($object)
    {
        $traits = $this->getAllTraits($object);

        foreach ($traits as $trait) {
            $traitName  = (new \ReflectionClass($trait))->getShortName();
            $methodName = 'init' . $traitName;

            if (method_exists($object, $methodName)) {
                $object->{$methodName}();
            }
        }
    }

    private function getAllTraits($object): array
    {
        $traits       = [];
        $currentClass = \get_class($object);

        while ($currentClass) {
            $traits       = array_merge($traits, class_uses($currentClass));
            $currentClass = get_parent_class($currentClass);
        }

        // Also get traits used by the traits
        $traits = array_merge($traits, $this->getTraitsFromTraits($traits));

        return array_unique($traits);
    }

    private function getTraitsFromTraits(array $traits): array
    {
        $allTraits = [];

        foreach ($traits as $trait) {
            $traitUses = class_uses($trait);
            $allTraits = array_merge($allTraits, $traitUses);
            $allTraits = array_merge($allTraits, $this->getTraitsFromTraits($traitUses));
        }

        return $allTraits;
    }
}
