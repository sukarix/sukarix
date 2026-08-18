<?php

declare(strict_types=1);

namespace Sukarix\Utils;

use mikehaertl\shellcommand\Command;

/**
 * Small wrapper around common git shell commands, used to expose the
 * running branch/version of an application (e.g. for diagnostics pages).
 *
 * @codeCoverageIgnore
 */
class CommandExecutor
{
    public static function gitBranch()
    {
        $command = new Command('/usr/bin/git --git-dir="' . \Base::instance()->ROOT . '/../.git" branch | sed -n \'/\* /s///p\'');
        $command->execute();

        return $command->getOutput();
    }

    public static function gitVersion()
    {
        $command = new Command('/usr/bin/git --git-dir="' . \Base::instance()->ROOT . '/../.git" describe --tags --always HEAD');
        $command->execute();

        return $command->getOutput();
    }
}
