<?php

declare(strict_types=1);

namespace Sukarix\Helpers;

use Sukarix\Configuration\Environment;

class Receiver extends Helper
{
    /** @var array<string, string> */
    protected array $uploads = [];

    /**
     * @return array<string, string>
     */
    public function uploadedFiles(): array
    {
        return $this->uploads;
    }

    /**
     * @return array{error: null|string}
     */
    public function uploadImageBase64(string $fileData, string $uploadSubDir, string $formFieldName): array
    {
        $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $fileData), true);
        if (false === $data) {
            return ['error' => 'upload.invalid_format'];
        }

        $mimeType = finfo_buffer(finfo_open(), $data, FILEINFO_MIME_TYPE);

        // check mime-type
        $allowedMimes = (array) $this->f3->get('UPLOAD.allowed.mimes.images');
        if (false === $ext = array_search($mimeType, $allowedMimes, true)) {
            return ['error' => 'upload.invalid_format'];
        }

        // check file size
        if (mb_strlen($data) > $this->getMaxSize()) {
            return ['error' => 'upload.exceeded_file_size'];
        }
        $name = $formFieldName . $this->f3->hash(microtime()) . '.' . $ext;

        $this->prepareUploadDirectory($uploadDirectory = $this->f3->get('UPLOADS') . $uploadSubDir);

        if (false === file_put_contents($this->f3->get('UPLOADS') . $uploadSubDir . $name, $data)) {
            return ['error' => 'upload.failed_to_move'];
        }
        $this->uploads[$formFieldName] = $uploadSubDir . $name;

        return [
            'error' => null,
        ];
    }

    /**
     * @param array<string, mixed>    $file
     * @param null|array<int, string> $allowedFiles
     *
     * @return array{error: null|string}
     */
    protected function upload(array $file, string $uploadDirectory, ?int $maxSize, ?array $allowedFiles, string $formFieldName, bool $useGeneratedName = false): array
    {
        // Undefined | Multiple Files | $_FILES Corruption Attack
        // If this request falls under any of them, treat it invalid.
        if (!isset($file['error']) || \is_array($file['error'])) {
            return ['error' => 'upload.invalid_parameters'];
        }

        // Check $file['error'] value.
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;

            case UPLOAD_ERR_NO_FILE:
                return ['error' => 'upload.no_file'];

            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['error' => 'upload.exceeded_file_size'];

            default:
                return ['error' => 'upload.unknown'];
        }

        if (null !== $maxSize && filesize($file['tmp_name']) > $maxSize) {
            return ['error' => 'upload.exceeded_file_size'];
        }

        // Check MIME Type by yourself because $file['mime'] must not be trusted.
        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
        if (null !== $allowedFiles && false === $ext = array_search($fileInfo->file($file['tmp_name']), $allowedFiles, true)) {
            return ['error' => 'upload.invalid_format'];
        }

        $this->prepareUploadDirectory($uploadDirectory);

        if ($useGeneratedName) {
            $fileName = $formFieldName;
        } else {
            $fileName = $formFieldName . $this->f3->hash($file['tmp_name']) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        }

        $destination = "{$uploadDirectory}/{$fileName}";
        if (!$this->saveUploadedFile($file, $destination)) {
            return ['error' => 'upload.failed_to_move'];
        }

        $this->uploads[$formFieldName] = str_replace($this->f3->get('UPLOADS'), '', $destination);

        return [
            'error' => null,
        ];
    }

    protected function getMaxSize(): int
    {
        $maxSizeValue = (int) $this->f3->get('UPLOAD.maxsize.image.value');
        $maxSizeExp   = $this->f3->get('UPLOAD.maxsize.image.exponent');

        return match ($maxSizeExp) {
            'KB'    => $maxSizeValue * 1024,
            'MB'    => $maxSizeValue * 1024 * 1024,
            default => $maxSizeValue,
        };
    }

    protected function saveUploadedFile($file, $destination)
    {
        return Environment::isTest() ? rename($file['tmp_name'], $destination) : move_uploaded_file($file['tmp_name'], $destination);
    }

    protected function prepareUploadDirectory($uploadDirectory): void
    {
        (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0o766, true) && !is_dir($uploadDirectory)) && throw new \RuntimeException(\sprintf('Directory "%s" was not created', $uploadDirectory));
    }
}
