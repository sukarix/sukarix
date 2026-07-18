<?php

declare(strict_types=1);

namespace Core;

use Sukarix\Core\Injector;
use Test\Scenario;

/**
 * Exercises the Sukarix dependency injector using the Statera test kit.
 *
 * @internal
 *
 * @coversNothing
 */
final class InjectorTest extends Scenario
{
    protected $group = 'Core Injector';

    /**
     * Registry aliases and class names that may be touched by the scenarios
     * below. They are cleaned between every scenario to keep each test
     * isolated, mirroring the previous PHPUnit tearDown() hook.
     *
     * @var list<string>
     */
    private array $registryKeys = [
        'service',
        'first',
        'second',
        'prefab',
        'external',
        Contract::class,
        PrefabService::class,
    ];

    /**
     * Reset F3 and Registry state before building a fresh test case so that
     * scenarios do not leak state into one another.
     */
    protected function newTest()
    {
        $this->cleanUp();

        return parent::newTest();
    }

    /**
     * @param $f3 \Base
     */
    public function testResolvesAndCachesConfiguredAlias($f3)
    {
        $test     = $this->newTest();
        $injector = new Injector(['service' => Dependency::class]);

        $service = $injector->get('service');

        $test->expect($service instanceof Dependency, 'Configured alias resolves to the mapped class');
        $test->expect($service === $injector->get('service'), 'Resolved service is cached on subsequent gets');
        $test->expect($service === \Registry::get('service'), 'Resolved service is stored in the Registry');

        return $test->results();
    }

    /**
     * @param $f3 \Base
     */
    public function testAutowiresConcreteClassDependencies($f3)
    {
        $test    = $this->newTest();
        $service = (new Injector())->get(Service::class);

        $test->expect($service instanceof Service, 'Concrete class is resolved by name');
        $test->expect($service->dependency instanceof Dependency, 'Concrete dependencies are autowired');

        return $test->results();
    }

    /**
     * @param $f3 \Base
     */
    public function testAutowiresInterfaceFromConfiguration($f3)
    {
        $test     = $this->newTest();
        $injector = new Injector([Contract::class => Implementation::class]);

        $service = $injector->get(ServiceWithContract::class);

        $test->expect($service->dependency instanceof Implementation, 'Interface is resolved to the configured implementation');

        return $test->results();
    }

    /**
     * @param $f3 \Base
     */
    public function testUsesParameterNameConfigurationBeforeDefaultValue($f3)
    {
        $test = $this->newTest();
        \Base::instance()->set('name', 'configured');

        $service = (new Injector())->get(ScalarDefaults::class);

        $test->expect('configured' === $service->name, 'Parameter name configuration is used over the default value');
        $test->expect(2 === $service->count, 'Default value is kept when no configuration exists');

        return $test->results();
    }

    /**
     * @param $f3 \Base
     */
    public function testKeepsLegacyScalarTypeConfiguration($f3)
    {
        $test = $this->newTest();
        \Base::instance()->set('string', 'legacy');

        $service = (new Injector())->get(RequiredString::class);

        $test->expect('legacy' === $service->value, 'Legacy scalar type configuration is kept');

        return $test->results();
    }

    /**
     * @param $f3 \Base
     */
    public function testKeepsLegacyDefaultsForRequiredBuiltInTypes($f3)
    {
        $test    = $this->newTest();
        $service = (new Injector())->get(RequiredBuiltins::class);

        $test->expect('' === $service->text, 'Required string defaults to empty');
        $test->expect(0 === $service->number, 'Required int defaults to 0');
        $test->expect(0.0 === $service->decimal, 'Required float defaults to 0.0');
        $test->expect(false === $service->enabled, 'Required bool defaults to false');
        $test->expect([] === $service->items, 'Required array defaults to empty');

        return $test->results();
    }

    /**
     * @param $f3 \Base
     */
    public function testResolvesClassMemberOfUnionType($f3)
    {
        $test     = $this->newTest();
        $injector = new Injector([Contract::class => Implementation::class]);

        $service = $injector->get(ServiceWithUnion::class);

        $test->expect($service->dependency instanceof Implementation, 'Union type resolves to the configured class member');

        return $test->results();
    }

    /**
     * @param $f3 \Base
     */
    public function testResolvesRegisteredIntersectionType($f3)
    {
        $test     = $this->newTest();
        $injector = new Injector();
        $both     = new BothContracts();
        $injector->set('service', $both);

        $service = $injector->get(ServiceWithIntersection::class);

        $test->expect($both === $service->dependency, 'Intersection type resolves to the registered instance');

        return $test->results();
    }

    /**
     * @param $f3 \Base
     */
    public function testUsesNullableDefaultForUnavailableClass($f3)
    {
        $test    = $this->newTest();
        $service = (new Injector())->get(ServiceWithOptionalDependency::class);

        $test->expect(null === $service->dependency, 'Nullable unavailable dependency defaults to null');

        return $test->results();
    }

    /**
     * @param $f3 \Base
     */
    public function testDetectsCircularDependencies($f3)
    {
        $test  = $this->newTest();
        $threw = false;

        try {
            (new Injector())->get(CircularA::class);
        } catch (\LogicException $e) {
            $threw = true;
            $test->expect(str_contains($e->getMessage(), 'Circular dependency detected'), 'Circular dependency raises a descriptive LogicException');
        }

        $test->expect($threw, 'Circular dependency throws a LogicException');

        return $test->results();
    }

    /**
     * @param $f3 \Base
     */
    public function testDoesNotMaskExceptionsThrownByConstructors($f3)
    {
        $test  = $this->newTest();
        $threw = false;

        try {
            (new Injector())->get(ServiceWithExplodingDependency::class);
        } catch (\DomainException $e) {
            $threw = true;
            $test->expect('Constructor failed' === $e->getMessage(), 'Constructor exception message is preserved');
        }

        $test->expect($threw, 'Constructor exceptions are not masked by the injector');

        return $test->results();
    }

    /**
     * @param $f3 \Base
     */
    public function testClearRemovesOwnedRegistryEntries($f3)
    {
        $test     = $this->newTest();
        $injector = new Injector();
        $injector->set('first', new Dependency());
        $injector->set('second', new Dependency());

        $injector->clear();

        $test->expect(false === \Registry::exists('first'), 'clear() removes the first owned Registry entry');
        $test->expect(false === \Registry::exists('second'), 'clear() removes the second owned Registry entry');

        return $test->results();
    }

    /**
     * @param $f3 \Base
     */
    public function testClearCanRemoveOneEntryWithoutRemovingOthers($f3)
    {
        $test     = $this->newTest();
        $injector = new Injector();
        $injector->set('first', new Dependency());
        $second = new Dependency();
        $injector->set('second', $second);

        $injector->clear('first');

        $test->expect(false === \Registry::exists('first'), 'clear(alias) removes only the targeted entry');
        $test->expect($second === $injector->get('second'), 'clear(alias) preserves the other entries');

        return $test->results();
    }

    /**
     * @param $f3 \Base
     */
    public function testClearAlsoResetsPrefabSingleton($f3)
    {
        $test     = $this->newTest();
        PrefabService::$instances = 0;
        $injector = new Injector(['prefab' => PrefabService::class]);
        $first    = $injector->get('prefab');

        $injector->clear('prefab');
        $second = $injector->get('prefab');

        $test->expect($first !== $second, 'clear() resets the prefab singleton');
        $test->expect(2 === PrefabService::$instances, 'Prefab is instantiated again after clear()');

        return $test->results();
    }

    /**
     * @param $f3 \Base
     */
    public function testHasRecognizesResolvableClassNames($f3)
    {
        $test = $this->newTest();

        $test->expect(true === (new Injector())->has(Dependency::class), 'has() recognises resolvable class names');

        return $test->results();
    }

    private function cleanUp(): void
    {
        foreach ($this->registryKeys as $key) {
            if (\Registry::exists($key)) {
                \Registry::clear($key);
            }
        }

        $f3 = \Base::instance();
        foreach (['name', 'string'] as $key) {
            if ($f3->exists($key)) {
                $f3->clear($key);
            }
        }
    }
}

final class Dependency {}

final class Service
{
    public function __construct(public Dependency $dependency) {}
}

interface Contract {}

interface AlternativeContract {}

final class Implementation implements Contract {}

final class ServiceWithContract
{
    public function __construct(public Contract $dependency) {}
}

final class ServiceWithUnion
{
    public function __construct(public AlternativeContract|Contract $dependency) {}
}

interface FirstContract {}

interface SecondContract {}

final class BothContracts implements FirstContract, SecondContract {}

final class ServiceWithIntersection
{
    public function __construct(public FirstContract&SecondContract $dependency) {}
}

final class ScalarDefaults
{
    public function __construct(public string $name = 'fallback', public int $count = 2) {}
}

final class RequiredString
{
    public function __construct(public string $value) {}
}

final class RequiredBuiltins
{
    /**
     * @param list<mixed> $items
     */
    public function __construct(
        public string $text,
        public int $number,
        public float $decimal,
        public bool $enabled,
        public array $items
    ) {}
}

interface UnavailableDependency {}

final class ServiceWithOptionalDependency
{
    public function __construct(public ?UnavailableDependency $dependency = null) {}
}

final class CircularA
{
    public function __construct(public CircularB $dependency) {}
}

final class CircularB
{
    public function __construct(public CircularA $dependency) {}
}

final class ExplodingDependency
{
    public function __construct()
    {
        throw new \DomainException('Constructor failed');
    }
}

final class ServiceWithExplodingDependency
{
    public function __construct(public ExplodingDependency $dependency) {}
}

final class PrefabService extends \Prefab
{
    public static int $instances = 0;

    public function __construct()
    {
        ++self::$instances;
    }
}
