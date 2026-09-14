<?php

declare(strict_types=1);

namespace Sukarix\Utils;

/**
 * Reads the privileges an application declares from the action classes that carry
 * them.
 *
 * The composer class map was the obvious source, but an action added after the
 * autoloader was dumped is missing from it, and its privilege then silently
 * disappears from the role matrix. The directory is the truth.
 */
class PrivilegeUtils
{
    /**
     * Privileges grouped by action namespace, each group's actions sorted.
     *
     * @param string $directory  directory the action classes live in
     * @param string $namespace  namespace those classes are rooted at
     * @param string $traitName  trait an action carries to declare a privilege
     *
     * @return array<string, list<string>>
     */
    public static function listSystemPrivileges(string $directory, string $namespace, string $traitName): array
    {
        $f3         = \Base::instance();
        $privileges = [];

        foreach (self::actionClasses($directory, $namespace) as $action) {
            if (!class_exists($action)) {
                continue;
            }

            if (!\in_array($traitName, (new \ReflectionClass($action))->getTraitNames(), true)) {
                continue;
            }

            $parts = explode('\\', $action);
            $name  = array_pop($parts);
            $group = array_pop($parts);

            // Several classes can share one privilege: a resource in its own
            // namespace has an index, an add and a delete.
            $privileges[$f3->snakecase($group)][$f3->snakecase($name)] = true;
        }

        foreach ($privileges as $group => $actions) {
            $actions = array_keys($actions);
            sort($actions);
            $privileges[$group] = $actions;
        }

        ksort($privileges);

        return $privileges;
    }

    /**
     * Action classes found under the directory, read from the files themselves.
     *
     * Only classes living in a namespace of their own are returned; the ones sitting
     * directly under the root are the shared base classes.
     *
     * @return list<string>
     */
    public static function actionClasses(string $directory, string $namespace): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $classes  = [];
        $files    = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        $rootSize = mb_strlen($directory) + 1;

        foreach ($files as $file) {
            if (!$file->isFile() || 'php' !== $file->getExtension()) {
                continue;
            }

            $relative = mb_substr($file->getPathname(), $rootSize, -4);

            if (!str_contains($relative, \DIRECTORY_SEPARATOR)) {
                continue;
            }

            $classes[] = rtrim($namespace, '\\') . '\\' . str_replace(\DIRECTORY_SEPARATOR, '\\', $relative);
        }

        sort($classes);

        return $classes;
    }
}
