<?php

declare(strict_types=1);

namespace Utils;

use Sukarix\Utils\PrivilegeUtils;
use Test\Scenario;

/**
 * @internal
 *
 * @coversNothing
 */
final class PrivilegeUtilsTest extends Scenario
{
    protected $group = 'Utils PrivilegeUtils';

    public function testPrivilegesAreReadFromTheActionDirectory($f3)
    {
        $privileges = PrivilegeUtils::listSystemPrivileges(
            $this->fixtureRoot(),
            'Fixtures\\Actions',
            'Fixtures\\Actions\\RequirePrivilegeTrait'
        );

        $test = $this->newTest();
        $test->expect(['rooms', 'users'] === array_keys($privileges), 'the groups are the action namespaces, sorted');
        $test->expect(['add', 'index'] === $privileges['rooms'], 'the actions of a group are sorted');
        $test->expect(['index'] === $privileges['users'], 'each group lists only its own actions');

        return $test->results();
    }

    public function testAnActionWithoutTheTraitCarriesNoPrivilege($f3)
    {
        $privileges = PrivilegeUtils::listSystemPrivileges(
            $this->fixtureRoot(),
            'Fixtures\\Actions',
            'Fixtures\\Actions\\RequirePrivilegeTrait'
        );

        $test = $this->newTest();
        $test->expect(!\in_array('no_privilege', $privileges['rooms'], true), 'an action without the trait is skipped');

        return $test->results();
    }

    public function testClassesDirectlyUnderTheRootAreSkipped($f3)
    {
        $classes = PrivilegeUtils::actionClasses($this->fixtureRoot(), 'Fixtures\\Actions');

        $test = $this->newTest();
        $test->expect(
            !\in_array('Fixtures\\Actions\\Base', $classes, true),
            'the shared base classes are not actions'
        );
        $test->expect(
            \in_array('Fixtures\\Actions\\Rooms\\Index', $classes, true),
            'an action in its own namespace is found'
        );

        return $test->results();
    }

    public function testAMissingDirectoryIsEmpty($f3)
    {
        $test = $this->newTest();
        $test->expect([] === PrivilegeUtils::actionClasses('/does/not/exist', 'Fixtures\\Actions'), 'a missing directory yields nothing');
        $test->expect(
            [] === PrivilegeUtils::listSystemPrivileges('/does/not/exist', 'Fixtures\\Actions', 'Whatever'),
            'and no privileges'
        );

        return $test->results();
    }

    private function fixtureRoot(): string
    {
        return \Base::instance()->get('ROOT') . '/tests/fixtures/Actions';
    }
}
