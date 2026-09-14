<?php

declare(strict_types=1);

namespace Suite;

use Core\InjectorTest;
use Core\ResponseTest;
use Core\SessionBootstrapTest;
use Core\SessionContractTest;
use Core\SessionCsrfTest;
use Core\CapabilityTokenTest;
use Core\CorsTest;
use Core\EnvironmentOverrideTest;
use Core\ApiActionTest;
use Core\HealthProbeTest;
use Core\ModelCleanupTest;
use Core\ModelPersistenceTest;
use Core\OrmDeprecationTest;
use Core\SecretBoxTest;
use Core\StatelessRouteTest;
use Test\TestGroup;

/**
 * Aggregates all Core test scenarios for the Sukarix library.
 *
 * @internal
 *
 * @coversNothing
 */
final class CoreTest extends TestGroup
{
    protected $classes = [
        InjectorTest::class,
        ResponseTest::class,
        SessionCsrfTest::class,
        SessionContractTest::class,
        SessionBootstrapTest::class,
        CapabilityTokenTest::class,
        CorsTest::class,
        EnvironmentOverrideTest::class,
        ApiActionTest::class,
        HealthProbeTest::class,
        ModelCleanupTest::class,
        ModelPersistenceTest::class,
        OrmDeprecationTest::class,
        SecretBoxTest::class,
        StatelessRouteTest::class,
    ];
}
