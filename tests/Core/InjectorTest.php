<?php

declare(strict_types=1);

namespace Sukarix\Tests\Core;

use PHPUnit\Framework\TestCase;
use Sukarix\Core\Injector;

/**
 * @internal
 *
 * @coversNothing
 */
final class InjectorTest extends TestCase
{
    /** @var list<string> */
    private array $registryKeys = [
        'service',
        'first',
        'second',
        'prefab',
        'external',
        Contract::class,
        PrefabService::class,
    ];

    protected function tearDown(): void
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

    public function testResolvesAndCachesConfiguredAlias(): void
    {
        $injector = new Injector(['service' => Dependency::class]);

        $service = $injector->get('service');

        self::assertInstanceOf(Dependency::class, $service);
        self::assertSame($service, $injector->get('service'));
        self::assertSame($service, \Registry::get('service'));
    }

    public function testAutowiresConcreteClassDependencies(): void
    {
        $service = (new Injector())->get(Service::class);

        self::assertInstanceOf(Service::class, $service);
        self::assertInstanceOf(Dependency::class, $service->dependency);
    }

    public function testAutowiresInterfaceFromConfiguration(): void
    {
        $injector = new Injector([Contract::class => Implementation::class]);

        $service = $injector->get(ServiceWithContract::class);

        self::assertInstanceOf(Implementation::class, $service->dependency);
    }

    public function testUsesParameterNameConfigurationBeforeDefaultValue(): void
    {
        \Base::instance()->set('name', 'configured');

        $service = (new Injector())->get(ScalarDefaults::class);

        self::assertSame('configured', $service->name);
        self::assertSame(2, $service->count);
    }

    public function testKeepsLegacyScalarTypeConfiguration(): void
    {
        \Base::instance()->set('string', 'legacy');

        $service = (new Injector())->get(RequiredString::class);

        self::assertSame('legacy', $service->value);
    }

    public function testKeepsLegacyDefaultsForRequiredBuiltInTypes(): void
    {
        $service = (new Injector())->get(RequiredBuiltins::class);

        self::assertSame('', $service->text);
        self::assertSame(0, $service->number);
        self::assertSame(0.0, $service->decimal);
        self::assertFalse($service->enabled);
        self::assertSame([], $service->items);
    }

    public function testResolvesClassMemberOfUnionType(): void
    {
        $injector = new Injector([Contract::class => Implementation::class]);

        $service = $injector->get(ServiceWithUnion::class);

        self::assertInstanceOf(Implementation::class, $service->dependency);
    }

    public function testResolvesRegisteredIntersectionType(): void
    {
        $injector = new Injector();
        $both     = new BothContracts();
        $injector->set('service', $both);

        $service = $injector->get(ServiceWithIntersection::class);

        self::assertSame($both, $service->dependency);
    }

    public function testUsesNullableDefaultForUnavailableClass(): void
    {
        $service = (new Injector())->get(ServiceWithOptionalDependency::class);

        self::assertNull($service->dependency);
    }

    public function testDetectsCircularDependencies(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        (new Injector())->get(CircularA::class);
    }

    public function testDoesNotMaskExceptionsThrownByConstructors(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Constructor failed');

        (new Injector())->get(ServiceWithExplodingDependency::class);
    }

    public function testClearRemovesOwnedRegistryEntries(): void
    {
        $injector = new Injector();
        $injector->set('first', new Dependency());
        $injector->set('second', new Dependency());

        $injector->clear();

        self::assertFalse(\Registry::exists('first'));
        self::assertFalse(\Registry::exists('second'));
    }

    public function testClearCanRemoveOneEntryWithoutRemovingOthers(): void
    {
        $injector = new Injector();
        $injector->set('first', new Dependency());
        $second = new Dependency();
        $injector->set('second', $second);

        $injector->clear('first');

        self::assertFalse(\Registry::exists('first'));
        self::assertSame($second, $injector->get('second'));
    }

    public function testClearAlsoResetsPrefabSingleton(): void
    {
        PrefabService::$instances = 0;
        $injector                 = new Injector(['prefab' => PrefabService::class]);
        $first                    = $injector->get('prefab');

        $injector->clear('prefab');
        $second = $injector->get('prefab');

        self::assertNotSame($first, $second);
        self::assertSame(2, PrefabService::$instances);
    }

    public function testHasRecognizesResolvableClassNames(): void
    {
        self::assertTrue((new Injector())->has(Dependency::class));
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
