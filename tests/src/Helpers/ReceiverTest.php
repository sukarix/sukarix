<?php

declare(strict_types=1);

namespace Helpers;

use Sukarix\Helpers\Receiver;
use Test\Scenario;

/**
 * @internal
 *
 * @coversNothing
 */
final class ReceiverTest extends Scenario
{
    // A 1x1 red PNG.
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    protected $group  = 'Helpers Receiver uploadImageBase64';

    public function testValidImageIsStoredAndTracked($f3)
    {
        $test = $this->newTest();
        $dir  = $this->prepareUploadsHive($f3);

        $receiver = Receiver::instance();
        $result   = $receiver->uploadImageBase64('data:image/png;base64,' . self::PNG, 'avatars/', 'avatar');

        $test->expect(null === $result['error'], 'a valid PNG upload has no error');
        $test->expect(isset($receiver->uploadedFiles()['avatar']), 'the stored path is tracked under the field name');
        $test->expect(is_file($dir . $receiver->uploadedFiles()['avatar']), 'the file was written to the uploads directory');

        $this->removeDir($dir);

        return $test->results();
    }

    public function testMalformedBase64IsRejectedWithoutThrowing($f3)
    {
        $test = $this->newTest();
        $this->prepareUploadsHive($f3);

        $receiver = Receiver::instance();
        $result   = $receiver->uploadImageBase64('data:image/png;base64,***not base64***', 'avatars/', 'avatar');

        $test->expect('upload.invalid_format' === $result['error'], 'malformed base64 input is rejected, not a fatal error');

        return $test->results();
    }

    public function testDisallowedMimeTypeIsRejected($f3)
    {
        $test = $this->newTest();
        $this->prepareUploadsHive($f3);

        $receiver = Receiver::instance();
        $result   = $receiver->uploadImageBase64('data:image/png;base64,' . base64_encode('plain text, not an image'), 'avatars/', 'avatar');

        $test->expect('upload.invalid_format' === $result['error'], 'decoded content whose mime type is not allow-listed is rejected');

        return $test->results();
    }

    public function testUploadStoresAGeneratedFile($f3)
    {
        $test = $this->newTest();
        $dir  = $this->prepareUploadsHive($f3);
        $f3->set('application.environment', 'test');

        $tmpName = $dir . 'source-' . uniqid();
        mkdir($dir, 0o755, true);
        file_put_contents($tmpName, base64_decode(self::PNG, true));

        $receiver = new class extends Receiver {
            public function callUpload(array $file, string $uploadDirectory, ?int $maxSize, ?array $allowedFiles, string $formFieldName): array
            {
                return $this->upload($file, $uploadDirectory, $maxSize, $allowedFiles, $formFieldName);
            }
        };

        $file   = ['error' => UPLOAD_ERR_OK, 'tmp_name' => $tmpName, 'name' => 'photo.png'];
        $result = $receiver->callUpload($file, mb_rtrim($dir, '/'), null, ['png' => 'image/png'], 'photo');

        $test->expect(null === $result['error'], 'a valid upload has no error');
        $test->expect(1 === \count($receiver->uploadedFiles()), 'the upload is tracked');
        $test->expect(is_file($dir . $receiver->uploadedFiles()['photo']), 'the tracked path has a separator between directory and filename, and points at the real file');

        $this->removeDir($dir);

        return $test->results();
    }

    public function testUploadRejectsAnUnexpectedErrorCode($f3)
    {
        $test = $this->newTest();
        $this->prepareUploadsHive($f3);

        $receiver = new class extends Receiver {
            public function callUpload(array $file): array
            {
                return $this->upload($file, '/tmp', null, null, 'photo');
            }
        };

        $result = $receiver->callUpload(['error' => UPLOAD_ERR_NO_FILE, 'tmp_name' => '', 'name' => '']);
        $test->expect('upload.no_file' === $result['error'], 'UPLOAD_ERR_NO_FILE is reported as upload.no_file');

        return $test->results();
    }

    private function prepareUploadsHive($f3): string
    {
        $dir = $f3->get('TEMP') . 'receiver-test-' . uniqid() . '/';
        $f3->set('UPLOADS', $dir);
        $f3->set('UPLOAD.allowed.mimes.images', ['png' => 'image/png', 'jpg' => 'image/jpeg']);
        $f3->set('UPLOAD.maxsize.image.value', 5);
        $f3->set('UPLOAD.maxsize.image.exponent', 'MB');

        return $dir;
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
