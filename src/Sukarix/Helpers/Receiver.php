<?php

namespace Sukarix\Helpers;

use Sukarix\Configuration\Environment;

class Receiver extends Helper
{
    protected function saveUploadedFile($file, $destination)
    {
        return Environment::isTest() ? rename($file['tmp_name'], $destination) : move_uploaded_file($file['tmp_name'], $destination);
    }

    protected function prepareUploadDirectory($uploadDirectory)
    {
        (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0o766, true) && !is_dir($uploadDirectory)) && throw new \RuntimeException(sprintf('Directory "%s" was not created', $uploadDirectory));
    }
}
