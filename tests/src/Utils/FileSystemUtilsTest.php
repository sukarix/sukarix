<?php

declare(strict_types=1);

namespace Utils;

use Sukarix\Utils\FileSystemUtils;
use Test\Scenario;

/**
 * Exercises the generic filesystem helpers in Sukarix\Utils\FileSystemUtils.
 *
 * @internal
 *
 * @coversNothing
 */
final class FileSystemUtilsTest extends Scenario
{
    protected $group = 'Utils FileSystemUtils';

    public function testCreateDirectory($f3)
    {
        $test = $this->newTest();
        $path = $this->tempDir('create');

        FileSystemUtils::createDirectory($path);
        $test->expect(is_dir($path), 'createDirectory creates the requested directory');

        // Calling it again on an already existing directory must not throw.
        FileSystemUtils::createDirectory($path);
        $test->expect(is_dir($path), 'createDirectory is idempotent for existing directories');

        $this->removeDir($path);

        return $test->results();
    }

    public function testIsDirectoryEmpty($f3)
    {
        $test = $this->newTest();
        $path = $this->tempDir('empty');
        mkdir($path, 0o755, true);

        $test->expect(true === FileSystemUtils::isDirectoryEmpty($path), 'isDirectoryEmpty is true for an empty directory');

        file_put_contents($path . '/file.txt', 'content');
        $test->expect(false === FileSystemUtils::isDirectoryEmpty($path), 'isDirectoryEmpty is false once a file exists');

        $test->expect(false === FileSystemUtils::isDirectoryEmpty($path . '/does-not-exist'), 'isDirectoryEmpty is false for a missing directory');

        $this->removeDir($path);

        return $test->results();
    }

    public function testScanDirectory($f3)
    {
        $test = $this->newTest();
        $path = $this->tempDir('scan');
        mkdir($path . '/nested', 0o755, true);
        file_put_contents($path . '/a.log', 'a');
        file_put_contents($path . '/nested/b.log', 'b');
        file_put_contents($path . '/c.txt', 'c');

        $files = FileSystemUtils::scanDirectory($path, '.log');
        sort($files);

        $test->expect(2 === \count($files), 'scanDirectory finds files matching the pattern recursively');
        $test->expect(str_ends_with($files[0], 'a.log') && str_ends_with($files[1], 'nested/b.log'), 'scanDirectory returns the matching file paths');

        $threw = false;

        try {
            FileSystemUtils::scanDirectory($path . '/missing', '.log');
        } catch (\InvalidArgumentException $e) {
            $threw = true;
        }
        $test->expect($threw, 'scanDirectory throws for a missing directory');

        $this->removeDir($path);

        return $test->results();
    }

    public function testEnsureDirectoryExists($f3)
    {
        $test = $this->newTest();
        $path = $this->tempDir('ensure');

        FileSystemUtils::ensureDirectoryExists($path);
        $test->expect(is_dir($path) && is_writable($path), 'ensureDirectoryExists creates a writable directory');

        $this->removeDir($path);

        return $test->results();
    }

    private function tempDir(string $suffix): string
    {
        return \Base::instance()->get('TEMP') . 'fsutils-' . $suffix . '-' . uniqid();
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($path);
    }
}
